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

namespace Admin\Controller;

use Admin\Model\CacheModel;

if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

class BackstageController extends BaseController
{
    public function indexAction()
    {
        $runInfo = array();
        $mcollected = D('Collected');
        $todayTime = strtotime(date('Y-m-d', time()));
        $runInfo['today_success'] = $mcollected->where(array('addtime' => array('GT', $todayTime), 'target' => array('EXP', " <> ''")))->count();
        $runInfo['today_error'] = $mcollected->where(array('addtime' => array('GT', $todayTime), 'error' => array('EXP', " <> ''")))->count();
        $runInfo['total_success'] = $mcollected->where("`target` <> ''")->count();
        $runInfo['total_error'] = $mcollected->where("`error` <> ''")->count();
        $runInfo['task_auto'] = D('Task')->where('`auto`=1')->count();
        $runInfo['task_other'] = D('Task')->where('`auto`=0')->count();
        $serverInfo = array('os' => php_uname('s') . ' ' . php_uname('r'), 'php' => PHP_VERSION, 'db' => C('DB_TYPE'), 'version' => $GLOBALS['config']['version'] ? $GLOBALS['config']['version'] : constant("SKYCAIJI_VERSION"), 'server' => $_SERVER["SERVER_SOFTWARE"], 'upload_max' => ini_get('upload_max_filesize'));
        if (stripos($serverInfo['db'], 'mysql') !== false) {
            $dbVersion = M()->query('SELECT VERSION() as v;');
            $serverInfo['db'] .= ' ' . ($dbVersion[0] ? $dbVersion[0]['v'] : '');
        }
        $runInfo['auto_status'] = '良好';
        if ($GLOBALS['config']['caiji']['auto']) {
            $lastTime = F('last_collect_time');
            $taskAutoCount = D('Task')->where(array('auto' => 1))->count();
            if ($taskAutoCount <= 0) {
                $serverInfo['caiji'] = '<a href="' . U('Admin/Task/list') . '">未设置自动采集任务</a>';
                $runInfo['auto_status'] = '无任务';
            } else {
                if ($lastTime > 0) {
                    $runInfo['auto_status'] = '运行良好';
                    $serverInfo['caiji'] = '最近采集：' . date('Y-m-d H:i:s', $lastTime);
                    if ($GLOBALS['config']['caiji']['run'] == 'backstage') {
                        if (NOW_TIME - $lastTime > 60 * ($GLOBALS['config']['caiji']['interval'] + 300)) {
                            $serverInfo['caiji'] .= '<p class="help-block">自动采集似乎停止了，请<a href="' . U('Admin/Setting/caiji') . '">重新保存设置</a>以便激活采集</p>';
                            $runInfo['auto_status'] = '停止运行';
                        }
                    }
                }
                $serverInfo['caiji'] .= ($serverInfo['caiji'] ? ' &nbsp;' : '') . '<a href="javascript:;" id="a_collect_now">实时采集</a>';
            }
        } else {
            $runInfo['auto_status'] = '已停止';
            $serverInfo['caiji'] = '<a href="' . U('Admin/Setting/caiji') . '">未开启自动采集</a>';
        }
        $upgradeDb = false;
        if (version_compare(D('Config')->getVersion(), SKYCAIJI_VERSION, '<')) {
            $upgradeDb = true;
        }
        $timeout = NOW_TIME - (3600 * 24 * 30);
        $mcacheSource = new CacheModel('source_url');
        $mcacheSource->where('dateline<' . $timeout)->delete();
        $mcacheLevel = new CacheModel('level_url');
        $mcacheLevel->where('dateline<' . $timeout)->delete();
        $GLOBALS['content_header'] = '后台管理';
        $GLOBALS['breadcrumb'] = breadcrumb(array('首页'));
        $this->assign('runInfo', $runInfo);
        $this->assign('serverInfo', $serverInfo);
        $this->assign('upgradeDb', $upgradeDb);
        $this->display('Backstage/index');
    }

    public function adminIndexAction()
    {
        $callback = isset($_GET[C('VAR_JSONP_HANDLER')]) ? $_GET[C('VAR_JSONP_HANDLER')] : C('DEFAULT_JSONP_HANDLER');
        $html = get_html('http://www.skycaiji.com/Store/Client/adminIndex?' . C('VAR_JSONP_HANDLER') . '=' . rawurlencode($callback), null, null, 'utf-8');
        header('Content-Type:application/json; charset=utf-8');
        exit($html);
    }
}