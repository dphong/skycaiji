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
if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

class StoreController extends BaseController
{
    public function isLoginAction()
    {
        if (empty($GLOBALS['user'])) {
            $this->error(L('user_error_is_not_admin'), U('Admin/Index/index', null, null, true));
        } else {
            $this->success();
        }
    }

    public function indexAction()
    {
        $GLOBALS['content_header'] = L('store');
        $GLOBALS['breadcrumb'] = breadcrumb(array(L('store')));
        $this->display();
    }

    public function installRuleAction()
    {
        $mrule = D('Rule');
        $rule = json_decode(base64_decode(I('post.rule')), true);
        $store_id = intval($rule['store_id']);
        if (empty($store_id)) {
            $this->error('规则id为空');
        }
        if (empty($rule['name'])) {
            $this->error('名称为空');
        }
        if (empty($rule['type'])) {
            $this->error('类型错误');
        }
        if (empty($rule['module'])) {
            $this->error('模块错误');
        }
        $rule['config'] = base64_decode($rule['config']);
        if (empty($rule['config'])) {
            $this->error('规则为空');
        }
        if ($store_id > 0) {
            $newRule = array('type' => $rule['type'], 'name' => $rule['name'], 'module' => $rule['module'], 'uptime' => ($rule['uptime'] > 0 ? $rule['uptime'] : NOW_TIME), 'config' => $rule['config']);
            $ruleData = $mrule->where(array('type' => $rule['type'], 'store_id' => $store_id))->find();
            if (empty($ruleData)) {
                $newRule['store_id'] = $store_id;
                $newRule['addtime'] = NOW_TIME;
                $ruleId = $mrule->add($newRule);
            } else {
                $mrule->where(array('id' => $ruleData['id']))->save($newRule);
                $ruleId = $ruleData['id'];
            }
            $this->success($ruleId);
        } else {
            $this->error('id错误');
        }
    }

    public function ruleUpdateAction()
    {
        $storeIds = I('store_ids');
        $storeIdList = array('collect' => array());
        foreach (array_keys($storeIdList) as $type) {
            if (preg_match_all('/\b' . $type . '\_(\d+)/i', $storeIds, $typeIds)) {
                $storeIdList[$type] = $typeIds[1];
            }
        }
        $uptimeList = array('status' => 1, 'data' => array());
        $mrule = D('Rule');
        if (!empty($storeIdList)) {
            foreach ($storeIdList as $type => $ids) {
                if (!empty($ids)) {
                    $cond = array();
                    $cond['type'] = $type;
                    $cond['store_id'] = array('in', $ids);
                    $uptimeList['data'][$type] = $mrule->field('`id`,`type`,`store_id`,`uptime`')->where($cond)->select(array('index' => 'store_id,uptime'));
                }
            }
        }
        $this->ajaxReturn($uptimeList, 'jsonp');
    }

    public function installCmsAction()
    {
        $cms = json_decode(base64_decode(I('post.cms')), true);
        $cms['code'] = base64_decode($cms['code']);
        if (empty($cms['app'])) {
            $this->error('应用id错误');
        }
        if (empty($cms['name'])) {
            $this->error('应用名错误');
        }
        if (empty($cms['code'])) {
            $this->error('不是可用的程序');
        }
        if (!empty($cms['tpl'])) {
            $cms['tpl'] = base64_decode($cms['tpl']);
        }
        $mapp = D('ReleaseApp');
        $mapp->addCms(array('app' => $cms['app'], 'name' => $cms['name'], 'desc' => $cms['desc'], 'uptime' => $cms['uptime']), $cms['code'], $cms['tpl']);
        $this->success();
    }

    public function cmsUpdateAction()
    {
        $storeApps = I('store_apps');
        if (preg_match_all('/\bcms\_(\w+)/i', $storeApps, $apps)) {
            $apps = $apps[1];
        }
        $uptimeList = array('status' => 1, 'data' => array());
        if (!empty($apps)) {
            $cond = array();
            $cond['module'] = 'cms';
            $cond['app'] = array('in', $apps);
            $uptimeList['data'] = D('ReleaseApp')->where($cond)->select(array('index' => 'app,uptime'));
        }
        $this->ajaxReturn($uptimeList, 'jsonp');
    }

    public function siteCertificationAction()
    {
        $op = I('get.op');
        if ($op == 'set_key') {
            $key = I('post.key');
            if (empty($key)) {
                $this->error('密钥错误');
            }
            F('site_certification', array('key' => $key, 'time' => NOW_TIME));
            $this->success(1);
        } else {
            $this->error('操作错误！');
        }
    }
}