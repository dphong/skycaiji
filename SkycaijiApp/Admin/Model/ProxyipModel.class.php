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
class ProxyipModel extends BaseModel
{
    protected $tableName = 'proxy_ip';
    public $setting;

    public function __construct()
    {
        parent::__construct();
        $this->setting = $GLOBALS['config']['proxy'];
    }

    public function get_ip($proxy_ip)
    {
        $proxyIp = null;
        if (empty($proxy_ip) || empty($proxy_ip['ip'])) {
            $proxyIp = null;
        }
        if (empty($proxy_ip['user'])) {
            $proxyIp = $proxy_ip['ip'];
        } else {
            $proxyIp = array($proxy_ip['ip'], $proxy_ip['user'], $proxy_ip['pwd']);
        }
        return $proxyIp;
    }

    public function get_usable_ip()
    {
        if (!empty($this->setting['open'])) {
            $cond = array();
            $cond['invalid'] = 0;
            if (!empty($this->setting['use'])) {
                if ($this->setting['use'] == 'num') {
                    $cond['num'] = array('lt', $this->setting['use_num']);
                } elseif ($this->setting['use'] == 'time') {
                    $cond['time'] = array(array('eq', 0), array('gt', time() - $this->setting['use_time'] * 60), 'or');
                }
            } else {
                $cond['num'] = array('lt', 1);
            }
            $proxy_ip = $this->where($cond)->find();
            if (empty($proxy_ip)) {
                if (!empty($this->setting['use'])) {
                    if ($this->setting['use'] == 'num') {
                        $this->where(1)->save(array('num' => 0));
                    } elseif ($this->setting['use'] == 'time') {
                        $this->where(1)->save(array('time' => 0));
                    }
                } else {
                    $this->where(1)->save(array('num' => 0));
                }
                $proxy_ip = $this->where($cond)->find();
            }
            if (!empty($proxy_ip)) {
                $upData = array();
                if (!empty($this->setting['use'])) {
                    if ($this->setting['use'] == 'num') {
                        $upData['num'] = $proxy_ip['num'] + 1;
                    } elseif ($this->setting['use'] == 'time') {
                        if (empty($proxy_ip['time'])) {
                            $upData['time'] = time();
                        }
                    }
                } else {
                    $upData['num'] = $proxy_ip['num'] + 1;
                }
                $this->where(array('ip' => $proxy_ip['ip']))->save($upData);
            }
            return $proxy_ip;
        }
        return null;
    }

    public function set_ip_failed($proxy_ip)
    {
        if (empty($this->setting['failed']) || $this->setting['failed'] <= 0) {
            return;
        }
        if (empty($proxy_ip)) {
            return;
        }
        $upData = array();
        $upData['failed'] = $proxy_ip['failed'] + 1;
        if ($upData['failed'] >= $this->setting['failed']) {
            $upData['invalid'] = 1;
        }
        $this->where(array('ip' => $proxy_ip['ip']))->save($upData);
    }
} ?>