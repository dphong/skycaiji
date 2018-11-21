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

namespace Admin\Event;
if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

class RcmsEvent extends ReleaseEvent
{
    public function setConfig($config)
    {
        $config['cms'] = I('cms/a');
        $config['cms_app'] = I('cms_app/a');
        if (empty($config['cms']['path'])) {
            $this->error('cms路径不能为空');
        }
        if (empty($config['cms']['app'])) {
            $this->error('cms应用不能为空');
        }
        if (empty($config['cms']['name'])) {
            $config['cms']['name'] = $this->cms_name($config['cms']['path']);
        }
        try {
            $releCms = A('Release/' . ucfirst($config['cms']['app']), 'Cms');
            $releCms->init($config['cms']['path']);
            $releCms->runCheck($config['cms_app']);
        } catch (\Exception $ex) {
            $this->error($ex->getMessage());
        }
        return $config;
    }

    public function export($collFieldsList, $options = null)
    {
        $releCmsClass = 'Release\\Cms\\' . ucfirst($this->config['cms']['app']) . 'Cms';
        $releCms = new $releCmsClass();
        $releCms->init(null, $this->release);
        $addedNum = 0;
        foreach ($collFieldsList as $collFieldsKey => $collFields) {
            $return = $releCms->runExport($collFields['fields']);
            if ($return['id'] > 0) {
                $addedNum++;
            }
            $this->record_collected($collFields['url'], $return, $this->release, $collFields['title']);
            unset($collFieldsList[$collFieldsKey]['fields']);
        }
        return $addedNum;
    }

    public function cms_name($cmsPath)
    {
        list($cmsPath, $cmsPathName) = explode('@', $cmsPath);
        $cmsPath = realpath($cmsPath);
        if (empty($cmsPath)) {
            return '';
        }
        static $cmsNames = array();
        $md5Path = md5($cmsPath);
        if (!isset($cmsNames[$md5Path])) {
            $cmsName = '';
            if (!empty($cmsPathName)) {
                $cmsName = $cmsPathName;
            } else {
                $cmsFiles = $this->cms_files();
                foreach ($cmsFiles as $cms => $cmsFile) {
                    $cmsFile = realpath($cmsPath . '/' . $cmsFile);
                    if (!empty($cmsFile) && file_exists($cmsFile)) {
                        $cmsName = $cms;
                        break;
                    }
                }
            }
            $cmsNames[$md5Path] = $cmsName;
        }
        return $cmsNames[$md5Path];
    }

    public function cms_name_list($cmsPath, $return = false)
    {
        $cmsPath = realpath($cmsPath);
        static $list = array();
        if ($return) {
            foreach ($list as $cms => $files) {
                $files = array_unique($files);
                $files = array_filter($files);
                $files = array_values($files);
                $list[$cms] = $files;
            }
            return empty($list) ? array() : $list;
        }
        if (!empty($cmsPath)) {
            $cmsName = $this->cms_name($cmsPath);
            if (!empty($cmsName)) {
                $list[$cmsName][] = $cmsPath;
            }
        }
    }

    public function cms_files()
    {
        static $files = array('discuz' => 'source/class/discuz/discuz_core.php', 'wordpress' => 'wp-includes/wp-db.php', 'dedecms' => 'include/dedetemplate.class.php', 'empirecms' => 'e/class/EmpireCMS_version.php', 'phpcms' => 'phpcms/base.php', 'destoon' => 'api/oauth/destoon.inc.php', 'ecshop' => 'includes/cls_ecshop.php', 'shopex' => 'plugins/app/shopex_stat/shopex_stat_modifiers.php', 'espcms' => 'adminsoft/include/inc_replace_mailtemplates.php', 'metinfo' => 'config/metinfo.inc.php', 'twcms' => 'twcms/config/config.inc.php', 'zblog' => 'zb_system/function/lib/zblogphp.php', 'phpwind' => 'actions/pweditor/modifyattach.php', 'xiunobbs' => 'xiunophp/xiunophp.php', 'skyuc' => 'includes/modules/integrates/skyuc.php', 'jieqicms' => 'themes/jieqidiv/theme.html', 'hadsky' => 'app/hadskycloudserver/index.php', 'mipcms' => 'app/article/Mipcms.php', 'maccms' => 'application/extra/maccms.php',);
        return $files;
    }
} ?>