<?php
/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 http://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  http://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */

namespace Install\Event;

use Think\Controller;
use Admin\Model\ConfigModel;

if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

class UpgradeDbEvent extends Controller
{
    public function success($message = '', $jumpUrl = '', $ajax = false)
    {
        parent::success($message, $jumpUrl, $ajax);
        exit();
    }

    public function run()
    {
        clear_dir(C('APPPATH') . '/Runtime');
        $url = U('Install/upgrade/admin', null, true, true);
        header('Location:' . $url);
        exit();
    }

    public function upgrade()
    {
        set_time_limit(0);
        clear_dir(C('APPPATH') . '/Runtime');
        load_data_config();
        $result = $this->execute_upgrade();
        if ($result['success']) {
            $mconfig = new ConfigModel();
            $mconfig->setVersion($this->get_skycaiji_version());
        }
        return $result;
    }

    public function get_skycaiji_version()
    {
        $newProgramConfig = file_get_contents(C('ROOTPATH') . '/SkycaijiApp/Common/Common/function.php');
        if (preg_match('/[\'\"]SKYCAIJI_VERSION[\'\"]\s*,\s*[\'\"](?P<v>[\d\.]+?)[\'\"]/i', $newProgramConfig, $programVersion)) {
            $programVersion = $programVersion['v'];
        } else {
            $programVersion = '';
        }
        return $programVersion;
    }

    public function check_exists_index($name, $indexs)
    {
        if (empty($name)) {
            return false;
        }
        $exists_index = false;
        foreach ($indexs as $k => $v) {
            if (strcasecmp($name, $v['key_name']) == 0) {
                $exists_index = true;
                break;
            }
        }
        return $exists_index;
    }

    public function check_exists_field($name, $columns)
    {
        if (empty($name)) {
            return false;
        }
        $exists_column = false;
        foreach ($columns as $k => $v) {
            if (strcasecmp($name, $v['field']) == 0) {
                $exists_column = true;
                break;
            }
        }
        return $exists_column;
    }

    public function modify_field_type($field, $type, $modifySql, $columns)
    {
        foreach ($columns as $v) {
            if (strcasecmp($field, $v['field']) == 0) {
                if (strcasecmp($type, $v['type']) != 0) {
                    M()->execute($modifySql);
                }
                break;
            }
        }
    }

    public function execute_upgrade()
    {
        $mconfig = new ConfigModel();
        $dbVersion = $mconfig->getVersion();
        $fileVersion = $this->get_skycaiji_version();
        if (empty($dbVersion)) {
            return array('success' => false, 'msg' => '未获取到数据库中的版本号');
        }
        if (empty($fileVersion)) {
            return array('success' => false, 'msg' => '未获取到项目文件的版本号');
        }
        if (version_compare($dbVersion, $fileVersion) >= 0) {
            return array('success' => true, 'msg' => '数据库已是最新版本，无需更新');
        }
        $methods = get_class_methods($this);
        $upgradeDbMethods = array();
        foreach ($methods as $method) {
            if (preg_match('/^upgrade_db_to(?P<ver>(\_\d+)+)$/', $method, $toVer)) {
                $toVer = str_replace('_', '.', trim($toVer['ver'], '_'));
                if (version_compare($toVer, $dbVersion) >= 1) {
                    if (version_compare($toVer, $fileVersion) <= 0) {
                        $upgradeDbMethods[$toVer] = $method;
                    }
                }
            }
        }
        if (empty($upgradeDbMethods)) {
            return array('success' => true, 'msg' => '暂无更新');
        }
        ksort($upgradeDbMethods);
        foreach ($upgradeDbMethods as $newVer => $upMethod) {
            try {
                $this->$upMethod();
                $mconfig->setVersion($newVer);
            } catch (\Exception $ex) {
                return array('success' => false, 'msg' => $ex->getMessage());
            }
        }
        clear_dir(C('APPPATH') . '/Runtime');
        return array('success' => true, 'msg' => '升级完毕');
    }

    public function upgrade_db_to_1_0_2()
    {
        rename(C('ROOTPATH') . '/SkycaijiApp/Admin/View/CPattern', C('ROOTPATH') . '/SkycaijiApp/Admin/View/Cpattern');
        rename(C('ROOTPATH') . '/SkycaijiApp/Admin/View/MyStore', C('ROOTPATH') . '/SkycaijiApp/Admin/View/Mystore');
    }

    public function upgrade_db_to_1_3()
    {
        $db_prefix = C('DB_PREFIX');
        $proxy_table = $db_prefix . 'proxy_ip';
        $exists = M()->query("show tables like '{$proxy_table}'");
        if (empty($exists)) {
            $addTable = <<<EOF
CREATE TABLE `{$proxy_table}` (
  `ip` varchar(100) NOT NULL,
  `user` varchar(100) NOT NULL DEFAULT '',
  `pwd` varchar(100) NOT NULL DEFAULT '',
  `invalid` tinyint(4) NOT NULL DEFAULT '0',
  `failed` int(11) NOT NULL DEFAULT '0',
  `num` int(11) NOT NULL DEFAULT '0',
  `time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ip`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8
EOF;
            M()->execute($addTable);
        }
        $columns_collected = M()->query("SHOW COLUMNS FROM `{$db_prefix}collected`");
        if (!$this->check_exists_field('titleMd5', $columns_collected)) {
            M()->execute("alter table `{$db_prefix}collected` add `titleMd5` varchar(32) NOT NULL DEFAULT ''");
        }
        $indexs_collected = M()->query("SHOW INDEX FROM `{$db_prefix}collected`");
        if (!$this->check_exists_index('ix_titlemd5', $indexs_collected)) {
            M()->execute("ALTER TABLE `{$db_prefix}collected` ADD INDEX ix_titlemd5 ( `titleMd5` )");
        }
        $columns_task = M()->query("SHOW COLUMNS FROM `{$db_prefix}task`");
        if (!$this->check_exists_field('config', $columns_task)) {
            M()->execute("alter table `{$db_prefix}task` add `config` mediumtext");
        }
        $columns_collector = M()->query("SHOW COLUMNS FROM `{$db_prefix}collector`");
        $this->modify_field_type('config', 'mediumtext', "alter table `{$db_prefix}collector` modify column `config` mediumtext", $columns_collector);
        $columns_release = M()->query("SHOW COLUMNS FROM `{$db_prefix}release`");
        $this->modify_field_type('config', 'mediumtext', "alter table `{$db_prefix}release` modify column `config` mediumtext", $columns_release);
        $columns_rule = M()->query("SHOW COLUMNS FROM `{$db_prefix}rule`");
        $this->modify_field_type('config', 'mediumtext', "alter table `{$db_prefix}rule` modify column `config` mediumtext", $columns_rule);
    }
} ?>