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

namespace Admin\Behavior;

use Think\Behavior;
use Admin\Controller\BaseController;

if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

class InitBehavior extends Behavior
{
    public function run(&$params)
    {
        session_start();
        C('COOKIE_PATH', __ROOT__);
        $curController = strtolower(CONTROLLER_NAME);
        if ('store' == $curController) {
            header('Access-Control-Allow-Origin:http://www.skycaiji.com');
            header('Access-Control-Allow-Credentials:true');
            header('Access-Control-Allow-Methods:POST,GET');
        }
        $muser = D('User');
        $s_userid = session('user_id');
        if (empty($s_userid)) {
            $login_history = cookie('login_history');
            if (!empty($login_history)) {
                $login_history = explode('|', $login_history);
                $user = $muser->where(array('username' => $login_history[0]))->find();
                if (!empty($user)) {
                    $user['username'] = strtolower($user['username']);
                    if ($user['username'] == $login_history[0] && $login_history[1] == md5($user['username'] . $user['password'])) {
                        session('user_id', $user['uid']);
                    }
                }
            }
            $s_userid = session('user_id');
        }
        if ($s_userid > 0) {
            $GLOBALS['user'] = $muser->getByUid($s_userid);
            $GLOBALS['user']['group'] = D('Usergroup')->getById($GLOBALS['user']['groupid']);
        }
        $baseContr = new BaseController();
        if (empty($GLOBALS['user']) || (empty($GLOBALS['user']['group']['founder']) && empty($GLOBALS['user']['group']['admin']))) {
            if (!in_array($curController, array('index', 'api'))) {
                $baseContr->error_msg(L('user_error_is_not_admin'), U('Admin/Index/index', null, null, true));
                exit();
            }
        } else {
            if ('index' == $curController && 'index' == strtolower(ACTION_NAME)) {
                $baseContr->success(L('user_auto_login'), U('Admin/Backstage/index', null, null, true));
            }
            C('TMPL_ACTION_ERROR', 'Common:error_admin');
            C('TMPL_ACTION_SUCCESS', 'Common:success_admin');
        }
        $mconfig = D('Config');
        $latestDate = $mconfig->max('dateline');
        $keyConfig = 'cache_config_all';
        $cacheConfig = F($keyConfig);
        $configList = array();
        if (empty($cacheConfig) || $cacheConfig['update_time'] != $latestDate) {
            $configDbList = $mconfig->select();
            foreach ($configDbList as $configItem) {
                $configItem = $mconfig->convertData($configItem);
                $configList[$configItem['cname']] = $configItem['data'];
            }
            F($keyConfig, array('update_time' => $latestDate, 'list' => $configList));
        } else {
            $configList = $cacheConfig['list'];
        }
        $GLOBALS['config'] = $configList;
        $GLOBALS['clientinfo'] = clientinfo();
        if (!empty($GLOBALS['clientinfo'])) {
            $GLOBALS['clientinfo'] = base64_encode(json_encode($GLOBALS['clientinfo']));
        }
        $usertoken = session('usertoken');
        if (empty($usertoken)) {
            $usertoken = rand(1, 9999999) . '_' . date('Y-m-d');
            $usertoken = md5($usertoken);
            session('usertoken', $usertoken);
        }
        $GLOBALS['usertoken'] = $usertoken;
    }
} ?>