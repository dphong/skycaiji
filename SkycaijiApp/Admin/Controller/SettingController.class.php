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

use Admin\Model\ProxyipModel;

if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

class SettingController extends BaseController
{
    public function siteAction()
    {
        $mconfig = D('Config');
        if (IS_POST) {
            $config = array();
            $config['verifycode'] = I('verifycode', 0, 'intval');
            $config['hidehome'] = I('hidehome', 0, 'intval');
            $config['login'] = I('login/a', 0, 'intval');
            if ($config['login']['limit']) {
                if (empty($config['login']['failed'])) {
                    $this->error('请设置失败次数');
                }
                if (empty($config['login']['time'])) {
                    $this->error('请设置锁定时间');
                }
            }
            $mconfig->set('site', $config);
            $this->success(L('op_success'), U('Setting/site'));
        } else {
            $GLOBALS['content_header'] = L('setting_site');
            $GLOBALS['breadcrumb'] = breadcrumb(array(array('url' => U('Setting/site'), 'title' => L('setting_site'))));
            $siteConfig = $mconfig->get('site', 'data');
            $this->assign('siteConfig', $siteConfig);
        }
        $this->display();
    }

    public function caijiAction()
    {
        $mconfig = D('Config');
        if (IS_POST) {
            $config = array();
            $config['auto'] = I('auto', 0, 'intval');
            $config['run'] = I('run');
            $config['num'] = I('num', 0, 'intval');
            $config['interval'] = I('interval', 0, 'intval');
            $config['timeout'] = I('timeout', 0, 'intval');
            $config['html_interval'] = I('html_interval', 0, 'intval');
            $config['real_time'] = I('real_time', 0, 'intval');
            $config['download_img'] = I('download_img', 0, 'intval');
            $config['img_path'] = trim(I('img_path', ''));
            $config['img_url'] = I('img_url', '', 'trim');
            $config['img_name'] = I('img_name', '');
            $config['img_timeout'] = I('img_timeout', 0, 'intval');
            $config['img_interval'] = I('img_interval', 0, 'intval');
            $config['img_max'] = I('img_max', 0, 'intval');
            if (!empty($config['img_path'])) {
                if (!preg_match('/(^\w+\:)|(^[\/\\\])/i', $config['img_path'])) {
                    $this->error('图片目录必须为绝对路径！');
                }
                if (!is_dir($config['img_path'])) {
                    $this->error('图片目录不存在！');
                }
                $img_path = realpath($config['img_path']);
                $root_path = rtrim(realpath(C('ROOTPATH')), '\\\/');
                if (preg_match('/^' . addslashes($root_path) . '\b/i', $img_path)) {
                    if (!preg_match('/^' . addslashes($root_path) . '[\/\\\]data[\/\\\].+/i', $img_path)) {
                        $this->error('图片保存到本程序中，目录必须在data文件夹里');
                    }
                }
            }
            if (!empty($config['img_url']) && !preg_match('/^\w+\:\/\//i', $config['img_url'])) {
                $this->error('图片链接地址必须以http://或者https://开头');
            }
            $mconfig->set('caiji', $config);
            if ($config['auto'] && $config['run'] == 'backstage') {
                @get_html(U('Admin/Index/backstage', null, false, true), null, array('timeout' => 1));
            }
            $this->success(L('op_success'), U('Setting/caiji'));
        } else {
            $GLOBALS['content_header'] = L('setting_caiji');
            $GLOBALS['breadcrumb'] = breadcrumb(array(array('url' => U('Setting/caiji'), 'title' => L('setting_caiji'))));
            $caijiConfig = $mconfig->get('caiji', 'data');
            $this->assign('caijiConfig', $caijiConfig);
        }
        $this->display();
    }

    public function proxyAction()
    {
        $mconfig = D('Config');
        $mproxy = new ProxyipModel();
        if (IS_POST) {
            $config = array();
            $ip_list = I('ip_list', '', 'trim');
            $user_list = I('user_list', '', 'trim');
            $pwd_list = I('pwd_list', '', 'trim');
            $ip_list = empty($ip_list) ? null : json_decode($ip_list, true);
            $user_list = empty($user_list) ? null : json_decode($user_list, true);
            $pwd_list = empty($pwd_list) ? null : json_decode($pwd_list, true);
            $config['open'] = I('open', 0, 'intval');
            $config['failed'] = I('failed', 0, 'intval');
            $config['use'] = strtolower(I('use'));
            $config['use_num'] = I('use_num', 0, 'intval');
            $config['use_time'] = I('use_time', 0, 'intval');
            if ('num' == $config['use'] && $config['use_num'] <= 0) {
                $this->error('每个IP使用多少次必须大于0');
            }
            if ('time' == $config['use'] && $config['use_time'] <= 0) {
                $this->error('每个IP使用多少分钟必须大于0');
            }
            if (!empty($ip_list) && is_array($ip_list)) {
                $mproxy->where(array('ip' => array('not in', $ip_list)))->delete();
                $ip_list = array_map('trim', $ip_list);
                $user_list = array_map('trim', $user_list);
                $pwd_list = array_map('trim', $pwd_list);
                foreach ($ip_list as $k => $v) {
                    if (empty($v)) {
                        continue;
                    }
                    $newData = array('ip' => $v, 'user' => $user_list[$k], 'pwd' => $pwd_list[$k], 'invalid' => 0, 'failed' => 0, 'num' => 0, 'time' => 0,);
                    if ($mproxy->where(array('ip' => $newData['ip']))->count() > 0) {
                        unset($newData['invalid']);
                        $mproxy->where(array('ip' => $newData['ip']))->save($newData);
                    } else {
                        $mproxy->add($newData, array(), true);
                    }
                }
            } else {
                $mproxy->where('1')->delete();
            }
            $mconfig->set('proxy', $config);
            $this->success(L('op_success'), U('Setting/Proxy'));
        } else {
            $GLOBALS['content_header'] = '代理设置';
            $GLOBALS['breadcrumb'] = breadcrumb(array(array('url' => U('Setting/Proxy'), 'title' => '代理设置')));
            $proxyConfig = $mconfig->get('proxy', 'data');
            $proxyConfig['ip_list'] = $mproxy->select();
            $this->assign('proxyConfig', $proxyConfig);
        }
        $this->display();
    }

    public function proxyBatchAction()
    {
        if (IS_POST) {
            $ips = I('ips');
            $fmt = I('format');
            $fmt = str_replace(array('[ip]', '[端口]', '[用户名]', '[密码]'), array('(?P<ip>(\d+\.)+\d+)', '(?P<port>\d+)', '(?P<user>[^\s]+)', '(?P<pwd>[^\s]+)'), $fmt);
            $ipList = array();
            if (preg_match_all('/[^\r\n]+/', $ips, $m_ips)) {
                foreach ($m_ips[0] as $ip) {
                    if (preg_match('/' . $fmt . '/', $ip, $ipInfo)) {
                        $ipList[] = array('ip' => $ipInfo['ip'] . ':' . $ipInfo['port'], 'user' => $ipInfo['user'], 'pwd' => $ipInfo['pwd'],);
                    }
                }
            }
            $this->success($ipList);
        } else {
            $this->display('proxyBatch');
        }
    }

    public function translateAction()
    {
        $mconfig = D('Config');
        if (IS_POST) {
            $config = array();
            $config['open'] = I('open', 0, 'intval');
            $config['api'] = I('api', '', 'strtolower');
            $config['baidu'] = I('baidu/a', null, 'trim');
            $config['youdao'] = I('youdao/a', null, 'trim');
            if (!empty($config['api'])) {
                if (empty($config[$config['api']])) {
                    $this->error('请填写api配置');
                }
                foreach ($config[$config['api']] as $k => $v) {
                    if (empty($v)) {
                        $this->error('请填写api配置');
                    }
                }
            }
            $mconfig->set('translate', $config);
            $this->success(L('op_success'), U('Setting/translate'));
        } else {
            $GLOBALS['content_header'] = '翻译设置';
            $GLOBALS['breadcrumb'] = breadcrumb(array(array('url' => U('Setting/translate'), 'title' => '翻译设置')));
            $transConfig = $mconfig->get('translate', 'data');
            $this->assign('transConfig', $transConfig);
            $this->display();
        }
    }

    public function emailAction()
    {
        $is_test = I('is_test', 0, 'intval');
        $mconfig = D('Config');
        if (IS_POST) {
            $config = array();
            $config['sender'] = I('sender');
            $config['email'] = I('email');
            $config['pwd'] = I('pwd');
            $config['smtp'] = I('smtp');
            $config['port'] = I('port');
            $config['type'] = I('type');
            if ($is_test) {
                $return = send_mail($config, $config['email'], $config['sender'], L('set_email_test_subject'), L('set_email_test_body'));
                if ($return === true) {
                    $this->success(L('set_email_test_body'));
                } else {
                    $this->error($return);
                }
            } else {
                $mconfig->set('email', $config);
                $this->success(L('op_success'), U('Setting/email'));
            }
        } else {
            $GLOBALS['content_header'] = L('setting_email');
            $GLOBALS['breadcrumb'] = breadcrumb(array(array('url' => U('Setting/email'), 'title' => L('setting_email'))));
            $emailConfig = $mconfig->get('email', 'data');
            $this->assign('emailConfig', $emailConfig);
        }
        $this->display();
    }

    public function cleanAction()
    {
        set_time_limit(1000);
        $path = realpath(realpath(C('ROOTPATH') . '/' . APP_PATH) . '/Runtime');
        clear_dir($path);
        $this->success();
    }
}