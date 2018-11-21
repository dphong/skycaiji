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

namespace Admin\Model;
class UserModel extends BaseModel
{
    public static function right_username($username, $name = 'username')
    {
        $return = array('name' => $name);
        if (!preg_match('/^.{3,15}$/i', $username)) {
            $return['msg'] = L('user_error_username');
        } else {
            $return['success'] = true;
        }
        return $return;
    }

    public static function right_email($email, $name = 'email')
    {
        $return = array('name' => $name, 'field' => 'email');
        if (!preg_match('/^[^\s]+\@([\w\-]+\.){1,}\w+$/i', $email)) {
            $return['msg'] = L('user_error_email');
        } else {
            $return['success'] = true;
        }
        return $return;
    }

    public static function right_pwd($pwd, $name = 'password')
    {
        $return = array('name' => $name);
        if (!preg_match('/^[a-zA-Z0-9\!\@\#\$\%\^\&\*]{6,20}$/i', $pwd)) {
            $return['msg'] = L('user_error_password');
        } else {
            $return['success'] = true;
        }
        return $return;
    }

    public static function right_repwd($pwd, $repwd, $name = 'repassword')
    {
        if ($pwd != $repwd) {
            return array('msg' => L('user_error_repassword'), 'name' => $name);
        } else {
            return array('success' => true, 'name' => $name);
        }
    }

    public static function right_groupid($groupid, $name = 'groupid')
    {
        $return = array('name' => $name);
        $count = D('Usergroup')->where(array('id' => $groupid))->count();
        if (empty($count) || $count <= 0) {
            $return['msg'] = L('user_error_groupid');
        } else {
            $return['success'] = true;
        }
        return $return;
    }

    public static function right_yzm($username, $yanzhengma, $name = 'yzm')
    {
        $yzmSname = 'send_yzm.' . md5($username);
        $yzmSession = session($yzmSname);
        $check = array('name' => $name);
        if (empty($yzmSession)) {
            $check['msg'] = L('yzm_error_please_send');
        } elseif (empty($yanzhengma)) {
            $check['msg'] = L('yzm_error_please_input');
        } elseif (abs(NOW_TIME - $yzmSession['time']) > C('YZM_EXPIRE')) {
            $check['msg'] = L('yzm_error_timeout');
        } elseif (strcasecmp($yanzhengma, $yzmSession['yzm']) !== 0) {
            $check['msg'] = L('yzm_error_yzm');
        } else {
            $check['success'] = true;
        }
        return $check;
    }

    public function checkUsername($username)
    {
        $check = self::right_username($username);
        if ($check['success']) {
            if ($this->where(array('username' => $username))->count()) {
                $check['msg'] = L('user_error_has_username');
                $check['success'] = false;
            }
        }
        return $check;
    }

    public function add_check($data)
    {
        $check = self::right_groupid($data['groupid']);
        if (!$check['success']) {
            return $check;
        }
        $check = $this->checkUsername($data['username']);
        if (!$check['success']) {
            return $check;
        }
        $check = self::right_pwd($data['password']);
        if (!$check['success']) {
            return $check;
        }
        $check = self::right_repwd($data['password'], $data['repassword']);
        if (!$check['success']) {
            return $check;
        }
        $check = self::right_email($data['email']);
        if (!$check['success']) {
            return $check;
        }
        return array('success' => true);
    }

    public function edit_check($data)
    {
        if (!empty($data['groupid'])) {
            $check = self::right_groupid($data['groupid']);
            if (!$check['success']) {
                return $check;
            }
        }
        if (!empty($data['password'])) {
            $check = self::right_pwd($data['password']);
            if (!$check['success']) {
                return $check;
            }
            $check = self::right_repwd($data['password'], $data['repassword']);
            if (!$check['success']) {
                return $check;
            }
        }
        $check = self::right_email($data['email']);
        if (!$check['success']) {
            return $check;
        }
        return array('success' => true);
    }
} ?>