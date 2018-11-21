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

use Admin\Model\DbCommonModel;

if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

class ReleaseController extends BaseController
{
    public function setAction()
    {
        $taskId = I('task_id', 0, 'intval');
        $releaseId = I('release_id', 0, 'intval');
        $mtask = D('Task');
        $mrele = D('Release');
        $taskData = $mtask->getById($taskId);
        if (empty($taskData)) {
            $this->error(L('task_error_empty_task'));
        }
        $releData = $mrele->where(array('task_id' => $taskData['id']))->find();
        if (IS_POST) {
            $newData = array('task_id' => $taskData['id'], 'addtime' => NOW_TIME, 'config' => array());
            if ($releaseId > 0) {
                $importRele = $mrele->where(array('id' => $releaseId))->find();
                $newData['module'] = $importRele['module'];
                $newData['config'] = $importRele['config'];
            } else {
                $newData['module'] = I('module', '', 'strtolower');
                $releObj = A('Admin/R' . $newData['module'], 'Event');
                $newData['config'] = $releObj->setConfig($newData['config']);
                $newData['config'] = serialize($newData['config']);
            }
            if (empty($newData['module'])) {
                $this->error(L('rele_error_null_module'));
            }
            if (empty($releData)) {
                $releId = $mrele->add($newData);
            } else {
                $releId = $releData['id'];
                $mrele->where(array('id' => $releData['id']))->save($newData);
            }
            if ($releId > 0) {
                $this->success(L('op_success'), U('Release/set?task_id=' . $taskId));
            } else {
                $this->error(L('op_failed'));
            }
        } else {
            $GLOBALS['content_header'] = L('rele_set');
            $GLOBALS['breadcrumb'] = breadcrumb(array(array('url' => U('Task/edit?id=' . $taskData['id']), 'title' => L('task') . L('separator') . $taskData['name']), L('rele_set')));
            $this->assign('taskData', $taskData);
            if (!empty($releData)) {
                $releData['config'] = unserialize($releData['config']);
                $config = $releData['config'];
                $this->assign('config', $config);
                $this->assign('releData', $releData);
            }
            $this->display();
        }
    }

    public function importAction()
    {
        $page = max(1, I('p', 0, 'intval'));
        $mrele = D('Release');
        $mtask = D('Task');
        $limit = 1;
        $cond = array();
        $taskCond = array();
        $count = $mrele->where($cond)->count();
        $releList = $mrele->where($cond)->order('`id` desc')->limit($limit)->page($page)->select();
        if ($count > 0) {
            $taskIds = array();
            foreach ($releList as $rele) {
                $taskIds[$rele['task_id']] = $rele['task_id'];
            }
            if (!empty($taskIds)) {
                $taskCond['id'] = array('in', $taskIds);
                $taskNames = $mtask->where($taskCond)->select(array('index' => 'id,name'));
                $this->assign('taskNames', $taskNames);
            }
            if ($count > $limit) {
                $pageCount = ceil($count / $limit);
                $cpage = new \Think\Page($count, $limit);
                $pagenav = bootstrap_pages($cpage->show());
                $this->assign('pagenav', $pagenav);
            }
        }
        $this->assign('releList', $releList);
        $this->display();
    }

    public function cmsDetectAction()
    {
        $acms = A('Admin/Rcms', 'Event');
        $acms->cms_name_list(C('ROOTPATH'));
        $acms->cms_name_list(C('ROOTPATH') . '/../');
        $prevPath = C('ROOTPATH') . '/../';
        if (is_dir($prevPath)) {
            $dp = dir($prevPath);
            while ($curPath = $dp->read()) {
                if ($curPath != '.' && $curPath != '..') {
                    $curPath = $prevPath . $curPath;
                    if (is_dir($curPath)) {
                        $acms->cms_name_list($curPath);
                    }
                }
            }
            $dp->close();
        }
        $nextPath = C('ROOTPATH') . '/';
        if (is_dir($nextPath)) {
            $dp = dir($nextPath);
            while ($curPath = $dp->read()) {
                if ($curPath != '.' && $curPath != '..') {
                    $curPath = $nextPath . $curPath;
                    if (is_dir($curPath)) {
                        $acms->cms_name_list($curPath);
                    }
                }
            }
            $dp->close();
        }
        $cmsList = $acms->cms_name_list(null, true);
        if (!empty($cmsList)) {
            $this->success($cmsList);
        } else {
            $this->error(L('rele_error_detect_null'));
        }
    }

    public function cmsBindAction()
    {
        $cmsSet = I('cms/a');
        $taskId = I('task_id', 0, 'intval');
        $cmsPath = $cmsSet['path'];
        if (empty($cmsPath)) {
            $this->error('cms路径不能为空');
        }
        $acms = A('Admin/Rcms', 'Event');
        $cmsName = $acms->cms_name($cmsPath);
        if (empty($cmsName)) {
            $this->error('未知的cms程序，请确保路径存在，如需指定CMS程序请在路径结尾加上@CMS程序名，例如：@discuz');
        }
        $cmsApp = $cmsSet['app'];
        $cmsApps = D('ReleaseApp')->where(array('module' => 'cms', 'app' => array('like', addslashes($cmsName) . '%')))->order('`uptime` desc')->select();
        foreach ($cmsApps as $k => $v) {
            if (!file_exists(C('ROOTPATH') . '/' . APP_PATH . 'Release/Cms/' . ucfirst($v['app']) . 'Cms.class.php')) {
                unset($cmsApps[$k]);
            }
        }
        if (!empty($cmsApps)) {
            $cmsApps = array_values($cmsApps);
        }
        if (!empty($cmsApp)) {
            $cmsApp = ucfirst($cmsApp);
            $releCms = A('Release/' . ucfirst($cmsApp), 'Cms');
            if (!empty($releCms)) {
                try {
                    $releCms->init($cmsPath, array('task_id' => $taskId));
                    $releCms->runBind();
                } catch (\Exception $ex) {
                    $this->error($ex->getMessage());
                }
                $this->assign('releCms', $releCms);
            }
        }
        $this->assign('cmsName', $cmsName);
        $this->assign('cmsApps', $cmsApps);
        $this->assign('cmsApp', $cmsApp);
        $this->display('cmsBind');
    }

    public function testAction()
    {
        set_time_limit(600);
        $releId = I('id', 0, 'intval');
        $releData = D('Release')->getById($releId);
        if (empty($releData)) {
            $this->echo_msg(L('rele_error_empty_rele'));
            exit();
        }
        $taskData = D('Task')->getById($releData['task_id']);
        if (empty($taskData)) {
            $this->echo_msg(L('task_error_empty_task'));
            exit();
        }
        D('Task')->loadConfig($taskData['config']);
        $collData = D('Collector')->where(array('task_id' => $taskData['id'], 'module' => $taskData['module']))->find();
        if (empty($collData)) {
            $this->echo_msg(L('coll_error_empty_coll'));
            exit();
        }
        $acoll = a_c($collData['module']);
        $acoll->init($collData);
        $fieldsList = $acoll->collect(1);
        if (empty($fieldsList) || !is_array($fieldsList)) {
            $this->echo_msg('没有采集到数据', 'orange');
        } else {
            $releObj = A('Admin/R' . strtolower($releData['module']), 'Event');
            $releObj->init($releData);
            if ('api' == $releData['module']) {
                $releObj->config['api']['cache_time'] = 0;
            }
            $releObj->export($fieldsList);
        }
    }

    public function dbTablesAction()
    {
        $releId = I('id', 0, 'intval');
        $mrele = D('Release');
        $releData = $mrele->where(array('id' => $releId))->find();
        if (empty($releData)) {
            $this->error(L('rele_error_empty_rele'));
        }
        $config = unserialize($releData['config']);
        $db_config = A('Admin/Rdb', 'Event')->get_db_config($config['db']);
        try {
            $dbClass = new DbCommonModel('', null, $db_config);
            $tables = $dbClass->db()->getTables();
        } catch (\Exception $ex) {
            $msg = $this->trans_db_msg($ex->getMessage());
            $this->error($msg);
        }
        $this->assign('tables', $tables);
        $html = $this->fetch('dbTables');
        $this->success($html);
    }

    public function dbConnectAction()
    {
        $op = I('get.op');
        $db = I('db/a', '', 'trim');
        $db_config = A('Admin/Rdb', 'Event')->get_db_config($db);
        $no_check = array('db_pwd');
        if ('db_names' == $op) {
            $no_check[] = 'db_name';
        }
        foreach ($db_config as $k => $v) {
            if (empty($v) && !in_array($k, $no_check)) {
                $this->error(L('error_null_input', array('str' => L('rele_' . $k))));
            }
        }
        try {
            $dbConn = new DbCommonModel('', null, $db_config);
            if (empty($dbConn)) {
                $this->error('数据库连接错误');
            }
            if ('db_names' == $op) {
                $dbNames = array();
                if ($db_config['db_type'] == 'mysql') {
                    $dbsData = $dbConn->query('show databases');
                    foreach ($dbsData as $dbDt) {
                        $dbNames[$dbDt['database']] = $dbDt['database'];
                    }
                } elseif ($db_config['db_type'] == 'oracle') {
                    $dbsData = $dbConn->query('SELECT * FROM v$database');
                    foreach ($dbsData as $dbDt) {
                        $dbNames[$dbDt['name']] = $dbDt['name'];
                    }
                }
                if (empty($dbNames)) {
                    $this->error('没有数据库');
                }
                sort($dbNames);
                $this->assign('dbNames', $dbNames);
                $html = $this->fetch('Release:dbNames');
                $this->success($html);
            } else {
                $dbConn->db()->getTables();
                $this->success(L('rele_success_db_ok'));
            }
        } catch (\Exception $ex) {
            $msg = $this->trans_db_msg($ex->getMessage());
            $this->error($msg);
        }
    }

    public function dbTableBindAction()
    {
        $releId = I('id', 0, 'intval');
        $table = I('table');
        $tables = explode(',', $table);
        $tables = array_filter($tables);
        $tables = array_values($tables);
        if (empty($table)) {
            $this->error('请选择表');
        }
        $mrele = D('Release');
        $mtask = D('Task');
        $mcoll = D('Collector');
        $releData = $mrele->where(array('id' => $releId))->find();
        if (empty($releData)) {
            $this->error(L('rele_error_empty_rele'));
        }
        $config = unserialize($releData['config']);
        $adb = A('Admin/Rdb', 'Event');
        $db_config = $adb->get_db_config($config['db']);
        try {
            $dbClass = new DbCommonModel('', null, $db_config);
            $fields = array();
            $field_values = array();
            foreach ($tables as $tbName) {
                $fields[$tbName] = $dbClass->db()->getFields($tbName);
                if (!empty($config['db_table']['field'][$tbName])) {
                    $tableFields = $config['db_table']['field'][$tbName];
                    if (!empty($tableFields)) {
                        $issetFields = array();
                        foreach ($fields[$tbName] as $k => $v) {
                            if (isset($tableFields[$k])) {
                                $issetFields[$k] = $v;
                            }
                        }
                        $fields[$tbName] = array_merge($issetFields, $fields[$tbName]);
                    }
                    $field_values[$tbName]['field'] = $tableFields;
                    $field_values[$tbName]['custom'] = $config['db_table']['custom'][$tbName];
                }
            }
            $taskData = $mtask->getById($releData['task_id']);
            if (!empty($taskData)) {
                $collFields = $adb->get_coll_fields($taskData['id'], $taskData['module']);
            }
        } catch (\Exception $ex) {
            $dbMsg = $this->trans_db_msg($ex->getMessage());
            $this->error($dbMsg);
        }
        $this->assign('collFields', $collFields);
        $this->assign('tables', $tables);
        $this->assign('fields', $fields);
        $this->assign('field_values', $field_values);
        $this->display('dbTableBind');
    }

    public function trans_db_msg($msg)
    {
        $msg = L('rele_error_db') . str_replace('Unknown database', L('error_unknown_database'), $msg);
        return $msg;
    }
}