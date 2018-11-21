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

use Admin\Model\UserModel;
use Admin\Model\CacheModel;
use Admin\Model\ConfigModel;

if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

class IndexController extends BaseController
{
    public function indexAction()
    {
        $this->display();
    }

    public function backstageAction()
    {
        if (empty($GLOBALS['config']['caiji']['auto'])) {
            $this->error('请先开启自动采集');
        }
        ignore_user_abort(true);
        set_time_limit(0);
        $mconfig = new ConfigModel();
        $caijiConfig = $mconfig->where(array('cname' => 'caiji'))->find();
        $caijiConfig = $mconfig->convertData($caijiConfig);
        if (empty($caijiConfig['data']['auto']) || $caijiConfig['data']['run'] != 'backstage') {
            $this->error('自动采集已停止');
        }
        $collectTime1 = time();
        try {
            @get_html(U('Admin/Api/collect', null, false, true));
        } catch (\Exception $ex) {
        }
        $collectTime2 = time();
        $minWaitTime = 60;
        $waitTime = 0;
        if ($GLOBALS['config']['caiji']['interval'] > 0) {
            $waitTime = 60 * $GLOBALS['config']['caiji']['interval'] - ($collectTime2 - $collectTime1);
        }
        $waitTime = $waitTime > $minWaitTime ? $waitTime : $minWaitTime;
        sleep($waitTime);
        sleep(10);
        if ($GLOBALS['config']['caiji']['auto']) {
            try {
                @get_html(U('Admin/Index/backstage', null, false, true), null, array('timeout' => 5));
            } catch (\Exception $ex) {
            }
        }
        exit();
    }

    public function caijiAction()
    {
        @get_html(U('Admin/Api/collect', null, false, true), null, array('timeout' => 1));
        $waitTime = $GLOBALS['config']['caiji']['interval'] * 60;
        $waitTime = $waitTime > 0 ? $waitTime : 3;
        $this->success('正在采集...', U('Admin/Index/caiji'), $waitTime);
    }

    public function collectAction()
    {
        A('Admin/Api', 'Controller')->collectAction();
    }

    public function apiTaskAction()
    {
        A('Admin/Api', 'Controller')->taskAction();
    }

    public function loginAction()
    {
        if (IS_POST) {
            if (!check_usertoken()) {
                $this->error(L('usertoken_error'));
            }
            $mcacheLogin = new CacheModel('login');
            $config_login = $GLOBALS['config']['site']['login'];
            $clientIpMd5 = md5(get_client_ip());
            if (!empty($config_login['limit'])) {
                $ipLoginData = $mcacheLogin->get($clientIpMd5, 'data');
                if ((NOW_TIME - $ipLoginData['lastdate']) < ($config_login['time'] * 3600) && $ipLoginData['failed'] >= $config_login['failed']) {
                    $this->error("您已登录失败{$ipLoginData['failed']}次，被锁定{$config_login['time']}小时");
                }
            }
            if (I('post.sublogin')) {
                $username = strtolower(trim(I('post.username')));
                $pwd = trim(I('post.password'));
                if ($GLOBALS['config']['site']['verifycode']) {
                    $verifycode = trim(I('post.verifycode'));
                    $check = check_verify($verifycode);
                    if (!$check['success']) {
                        $this->error($check['msg']);
                    }
                }
                $check = UserModel::right_username($username);
                if (!$check['success']) {
                    $this->error($check['msg']);
                }
                $check = UserModel::right_pwd($pwd);
                if (!$check['success']) {
                    $this->error($check['msg']);
                }
                $muser = new UserModel();
                $userData = $muser->where(array('username' => $username))->find();
                if (empty($userData) || $userData['password'] != pwd_encrypt($pwd)) {
                    if (!empty($config_login['limit'])) {
                        $ipLoginData = $mcacheLogin->get($clientIpMd5, 'data');
                        if (!empty($ipLoginData)) {
                            if ((NOW_TIME - $ipLoginData['lastdate']) < ($config_login['time'] * 3600)) {
                                $ipLoginData['failed']++;
                            } else {
                                $ipLoginData['lastdate'] = NOW_TIME;
                                $ipLoginData['failed'] = 1;
                            }
                        } else {
                            $ipLoginData['lastdate'] = NOW_TIME;
                            $ipLoginData['failed'] = 1;
                        }
                        $ipLoginData['ip'] = get_client_ip();
                        $mcacheLogin->set($clientIpMd5, $ipLoginData);
                        $this->error(L('user_error_login') . "失败{$config_login['failed']}次将被锁定{$config_login['time']}小时，已失败{$ipLoginData['failed']}次");
                    }
                    $this->error(L('user_error_login'));
                }
                if (I('post.auto')) {
                    cookie('login_history', $username . '|' . md5($username . $userData['password']), array('expire' => 3600 * 24 * 15));
                }
                session('user_id', $userData['uid']);
                $serverinfo = I('serverinfo');
                if (empty($serverinfo)) {
                    $this->success(L('user_login_in'), U('Admin/Backstage/index'));
                } else {
                    $this->ajax_js(1, L('user_login_in'), 'window.parent.postMessage("login_success","http://www.skycaiji.com");');
                }
            } else {
                $this->error(L('user_error_sublogin'));
            }
        } else {
            $this->display('index');
        }
    }

    public function logoutAction()
    {
        cookie('login_history', null);
        unset($GLOBALS['user']);
        session('user_id', null);
        $this->success(L('op_success'), U('Admin/Index/index'));
    }

    public function verifyAction()
    {
        $config = array('fontSize' => 50, 'fontttf' => '5.ttf', 'length' => 3, 'useCurve' => false, 'useNoise' => true);
        ob_clean();
        $verify = new \Think\Verify($config);
        $verify->entry();
    }

    public function find_passwordAction()
    {
        $username = trim(I('get.username'));
        if ($username) {
            $username = base64_decode($username);
        } else {
            $username = trim(I('post.username'));
        }
        $step = max(1, I('step', 1, 'intval'));
        $stepSname = 'find_password_step.' . md5($username);
        $stepSession = session($stepSname);
        $muser = D('User');
        if ($step > 1) {
            if (strcasecmp(('step' . $step), $stepSession['step']) !== 0) {
                $this->error(L('find_pwd_error_step'), U('Index/find_password'));
            }
            if (empty($stepSession['user'])) {
                $this->error(L('find_pwd_error_none_user'));
            }
        }
        if (IS_POST) {
            if (I('post.subForPwd')) {
                if (empty($username)) {
                    $this->error(L('find_pwd_error_username'));
                }
                if ($step === 1) {
                    if (!check_usertoken()) {
                        $this->error(L('usertoken_error'));
                    }
                    if ($GLOBALS['config']['site']['verifycode']) {
                        $verifycode = trim(I('verifycode'));
                        $check = check_verify($verifycode);
                        if (!$check['success']) {
                            $this->error($check['msg']);
                        }
                    }
                    $username_is_email = false;
                    $check = UserModel::right_email($username);
                    if ($check['success']) {
                        $username_is_email = true;
                    }
                    if ($username_is_email) {
                        $emailCount = $muser->where(array('email' => $username))->count();
                        if ($emailCount <= 0) {
                            $this->error(L('find_pwd_error_none_email'));
                        } elseif ($emailCount > 1) {
                            $this->error(L('find_pwd_error_multiple_emails'));
                        } else {
                            $userData = $muser->where(array('email' => $username))->find();
                        }
                    } else {
                        $userData = $muser->where(array('username' => $username))->find();
                    }
                    if (empty($userData)) {
                        $this->error(L('find_pwd_error_none_user'));
                    }
                    session($stepSname, array('step' => 'step2', 'user' => $userData));
                    $this->success(L('redirecting'), U('Index/find_password?step=2&username=' . base64_encode($username)));
                } elseif ($step === 2) {
                    $yzm = trim(I('yzm'));
                    $check = UserModel::right_yzm($username, $yzm);
                    if (!$check['success']) {
                        $this->error($check['msg']);
                    }
                    $stepSession['step'] = 'step3';
                    session($stepSname, $stepSession);
                    $this->success(L('redirecting'), U('Index/find_password?step=3&username=' . base64_encode($username)));
                } elseif ($step === 3) {
                    $pwd = trim(I('password'));
                    $repwd = trim(I('repassword'));
                    $check = UserModel::right_pwd($pwd);
                    if (!$check['success']) {
                        $this->error($check['msg']);
                    }
                    $check = UserModel::right_repwd($pwd, $repwd);
                    if (!$check['success']) {
                        $this->error($check['msg']);
                    }
                    $muser->where(array('username' => $stepSession['user']['username']))->save(array('password' => pwd_encrypt($pwd)));
                    session($stepSname, null);
                    $this->success(L('find_pwd_success'), U('Admin/Index/index'));
                } else {
                    $this->error(L('find_pwd_error_step'), U('Index/find_password'));
                }
            } else {
                $this->error(L('find_pwd_error_post'));
            }
        } else {
            if ($step === 2) {
                $emailStatus = array('success' => false, 'msg' => '');
                if (empty($GLOBALS['config']['email'])) {
                    $emailStatus['msg'] = L('config_error_none_email');
                } else {
                    $waitTime = 60;
                    $waitSname = 'send_yzm_wait';
                    $passTime = abs(NOW_TIME - session($waitSname));
                    if ($passTime <= $waitTime) {
                        $emailStatus['msg'] = L('find_pwd_email_wait', array('seconds' => $waitTime - $passTime));
                    } else {
                        $expire = C('YZM_EXPIRE');
                        $minutes = floor($expire / 60);
                        $yzm = mt_rand(100000, 999999);
                        session($waitSname, NOW_TIME);
                        $mailReturn = send_mail($GLOBALS['config']['email'], $stepSession['user']['email'], $stepSession['user']['username'], L('find_pwd_email_subject'), L('find_pwd_email_body', array('yzm' => $yzm, 'minutes' => $minutes)));
                        if ($mailReturn == true) {
                            $yzmSname = 'send_yzm.' . md5($username);
                            session(array('name' => $yzmSname, 'expire' => $expire));
                            session($yzmSname, array('yzm' => $yzm, 'time' => NOW_TIME));
                            $emailStatus['success'] = true;
                            $emailStatus['msg'] = L('find_pwd_sended', array('email' => preg_replace('/.{2}\@/', '**@', $stepSession['user']['email'])));
                        } else {
                            $emailStatus['msg'] = L('find_pwd_email_failed');
                        }
                    }
                }
                $this->assign('emailStatus', $emailStatus);
            }
            $this->assign('userData', $stepSession['user']);
            $this->assign('username', $username);
            $this->assign('step', $step);
            $this->display();
        }
    }

    public function createJsLangAction()
    {
        $langs = C('LANG_LANGS');
        $langs['zh-cn'] = 'zh-cn';
        foreach ($langs as $lk => $lv) {
            $module_file = MODULE_PATH . 'Lang/' . $lv . '.php';
            $module_lang = include $module_file;
            $module_lang = is_array($module_lang) ? $module_lang : array();
            $common_file = COMMON_PATH . 'Lang/' . $lv . '.php';
            $common_lang = include $common_file;
            $common_lang = is_array($common_lang) ? $common_lang : array();
            $tpl_lang = array_merge($common_lang, $module_lang);
            $tpl_lang = 'var tpl_lang=' . json_encode($tpl_lang) . ';';
            \Think\Storage::put(dirname(THINK_PATH) . '/Public/js/langs/' . $lv . '.js', $tpl_lang, 'F');
            echo "ok{$lv}<br>";
        }
    }

    public function checkRepeatLangAction()
    {
        $file = MODULE_PATH . 'Lang/zh-cn.php';
        $txt = file_get_contents($file);
        $repeatList = array();
        if (preg_match_all('/[\'\"](\w+)[\'\"]\s*\=\s*\>\s*/', $txt, $keys)) {
            $keys = $keys[1];
            foreach ($keys as $i => $key) {
                if (in_array($key, array_slice($keys, $i + 1))) {
                    $repeatList[] = $key;
                }
            }
        }
        print_r($repeatList);
    }

    public function site_certificationAction()
    {
        $keyFile = F('site_certification');
        $key = $keyFile['key'];
        if (abs(NOW_TIME - $keyFile['time']) > 60) {
            $key = '';
        }
        exit($key);
    }

    public function clientinfoAction()
    {
        $this->ajaxReturn(clientinfo());
    }
}