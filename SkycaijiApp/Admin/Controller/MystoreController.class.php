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

class MystoreController extends BaseController
{
    public function indexAction()
    {
        redirect(U('Mystore/collect'));
    }

    public function collectAction()
    {
        $mrule = D('Rule');
        $type = 'collect';
        $module = I('module');
        $page = max(1, I('p', 0, 'intval'));
        $pageParams = array();
        $pageParams['type'] = $type;
        $cond = array('type' => $type);
        if (!empty($module)) {
            $cond = array('module' => $module);
            $pageParams['module'] = $module;
        }
        $sortBy = I('sort', 'desc');
        $sortBy = ($sortBy == 'asc') ? 'asc' : 'desc';
        $orderKey = I('order');
        $this->assign('sortBy', $sortBy);
        $this->assign('orderKey', $orderKey);
        $orderBy = !empty($orderKey) ? ($orderKey . ' ' . $sortBy) : '`id` desc';
        $limit = 20;
        $count = $mrule->where($cond)->count();
        $ruleList = $mrule->where($cond)->order($orderBy)->limit($limit)->page($page)->select();
        if ($count > 0) {
            if ($count > $limit) {
                $pageCount = ceil($count / $limit);
                $cpage = new \Think\Page($count, $limit);
                if (!empty($pageParams)) {
                    $cpage->parameter = array_merge($cpage->parameter, $pageParams);
                }
                $pagenav = bootstrap_pages($cpage->show());
                $this->assign('pagenav', $pagenav);
            }
        }
        $GLOBALS['content_header'] = '已下载';
        $GLOBALS['breadcrumb'] = breadcrumb(array(array('url' => U('Mystore/index'), 'title' => '已下载'), L('rule_' . $type)));
        $this->assign('ruleList', $ruleList);
        $this->display('rules' . ($_GET['tpl'] ? '_' . $_GET['tpl'] : ''));
    }

    public function ruleOpAction()
    {
        $id = I('id', 0, 'intval');
        $op = I('get.op');
        if (empty($op)) {
            $op = I('post.op');
        }
        $ops = array('item' => array('delete'), 'list' => array('deleteall', 'check_store_update'));
        if (!in_array($op, $ops['item']) && !in_array($op, $ops['list'])) {
            $this->error(L('invalid_op'));
        }
        $mrule = D('Rule');
        if ($op == 'delete') {
            $mrule->where(array('id' => $id))->delete();
            $this->success(L('delete_success'));
        } elseif ($op == 'deleteall') {
            $ids = I('ids');
            if (is_array($ids) && count($ids) > 0) {
                $mrule->where(array('id' => array('in', $ids)))->delete();
            }
            $this->success(L('op_success'), U('Mystore/collect'));
        } elseif ($op == 'check_store_update') {
            $ids = I('ids');
            if (!empty($ids)) {
                $ruleList = D('Rule')->where(array('id' => array('in', $ids)))->select(array('index' => 'store_id'));
            } else {
                $ruleList = array();
            }
            $uptimeList = array();
            if (!empty($ruleList)) {
                $storeIds = implode(',', array_keys($ruleList));
                $uptimeList = get_html('http://www.skycaiji.com/Store/Client/collectUpdate?ids=' . rawurlencode($storeIds));
                $uptimeList = json_decode($uptimeList, true);
            }
            if (!empty($uptimeList)) {
                $updateList = array();
                foreach ($uptimeList as $storeId => $storeUptime) {
                    if ($storeUptime > 0 && $storeUptime > $ruleList[$storeId]['uptime']) {
                        $updateList[] = $ruleList[$storeId]['id'];
                    }
                }
                $this->success($updateList);
            } else {
                $this->error();
            }
        }
    }

    public function releaseAppAction()
    {
        $page = max(1, I('p', 0, 'intval'));
        $pageParams = array();
        $cond = array();
        $sortBy = I('sort', 'desc');
        $sortBy = ($sortBy == 'asc') ? 'asc' : 'desc';
        $orderKey = I('order');
        $this->assign('sortBy', $sortBy);
        $this->assign('orderKey', $orderKey);
        $orderBy = !empty($orderKey) ? ($orderKey . ' ' . $sortBy) : '`id` desc';
        $mapp = D('ReleaseApp');
        $limit = 20;
        $count = $mapp->where($cond)->count();
        $appList = $mapp->where($cond)->order($orderBy)->limit($limit)->page($page)->select();
        if ($count > 0) {
            if ($count > $limit) {
                $pageCount = ceil($count / $limit);
                $cpage = new \Think\Page($count, $limit);
                if (!empty($pageParams)) {
                    $cpage->parameter = array_merge($cpage->parameter, $pageParams);
                }
                $pagenav = bootstrap_pages($cpage->show());
                $this->assign('pagenav', $pagenav);
            }
        }
        $GLOBALS['content_header'] = '已下载';
        $GLOBALS['breadcrumb'] = breadcrumb(array(array('url' => U('Mystore/index'), 'title' => '已下载'), '发布应用'));
        $this->assign('appList', $appList);
        $this->display('releaseApp');
    }

    public function releaseAppOpAction()
    {
        $id = I('id', 0, 'intval');
        $op = I('get.op');
        if (empty($op)) {
            $op = I('post.op');
        }
        $ops = array('item' => array('delete'), 'list' => array('deleteall', 'check_store_update'));
        if (!in_array($op, $ops['item']) && !in_array($op, $ops['list'])) {
            $this->error(L('invalid_op'));
        }
        $mapp = D('ReleaseApp');
        if ($op == 'delete') {
            $mapp->where(array('id' => $id))->delete();
            $this->success(L('delete_success'));
        } elseif ($op == 'deleteall') {
            $ids = I('ids');
            if (is_array($ids) && count($ids) > 0) {
                $mapp->where(array('id' => array('in', $ids)))->delete();
            }
            $this->success(L('op_success'), U('Mystore/ReleaseApp'));
        } elseif ($op == 'check_store_update') {
            $ids = I('ids');
            $appList = D('ReleaseApp')->where(array('module' => 'cms', 'id' => array('in', $ids)))->select(array('index' => 'app'));
            $uptimeList = array();
            if (!empty($appList)) {
                $apps = implode(',', array_keys($appList));
                $uptimeList = get_html('http://www.skycaiji.com/Store/Client/cmsUpdate?apps=' . rawurlencode($apps));
                $uptimeList = json_decode($uptimeList, true);
            }
            if (!empty($uptimeList)) {
                $updateList = array();
                foreach ($uptimeList as $app => $storeUptime) {
                    if ($storeUptime > 0 && $storeUptime > $appList[$app]['uptime']) {
                        $updateList[] = $appList[$app]['id'];
                    }
                }
                $this->success($updateList);
            } else {
                $this->error();
            }
        }
    }
}