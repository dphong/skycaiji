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

class TaskController extends BaseController
{
    public function indexAction()
    {
        $this->display();
    }

    public function listAction()
    {
        $page = I('p', 1, 'intval');
        $page = max(1, $page);
        $show = strtolower(I('show', 'list'));
        if (!in_array($show, array('list', 'folder'))) {
            $show = 'list';
        }
        $mtaskgroup = D('Taskgroup');
        $mtask = D('Task');
        if ($show == 'folder') {
            $tgSelect = $mtaskgroup->getLevelSelect();
            $tgSelect = preg_replace('/<select[^<>]*>/i', "$0<option value=''>" . L('all') . "</option>", $tgSelect);
            $this->assign('tgSelect', $tgSelect);
        } elseif ($show == 'list') {
            $sortBy = I('sort', 'desc');
            $sortBy = ($sortBy == 'asc') ? 'asc' : 'desc';
            $orderKey = I('order');
            $this->assign('sortBy', $sortBy);
            $this->assign('orderKey', $orderKey);
            $orderBy = !empty($orderKey) ? ($orderKey . ' ' . $sortBy) : '`sort` desc';
            $search['tg_id'] = I('tg_id');
            $search['name'] = I('name');
            $search['module'] = I('module');
            $search['show'] = 'list';
            $limit = 20;
            $cond = array();
            if (!empty($search['name'])) {
                $cond['name'] = array('like', '%' . addslashes($search['name']) . '%');
            }
            if (!empty($search['module'])) {
                $cond['module'] = $search['module'];
            }
            $this->assign('search', $search);
            if (is_numeric($search['tg_id'])) {
                if ($search['tg_id'] > 0) {
                    $tgData = $mtaskgroup->getById($search['tg_id']);
                    if (empty($tgData)) {
                        $this->error(L('task_error_empty_tg'));
                    }
                    $subTgList = $mtaskgroup->where(array('parent_id' => $tgData['id']))->select(array('index' => 'id,name'));
                    $subTgList[$tgData['id']] = $tgData['name'];
                    $cond['tg_id'] = array('in', array_keys($subTgList));
                    $this->assign('tgList', $subTgList);
                } else {
                    $cond['tg_id'] = 0;
                }
                $taskList = $mtask->where($cond)->order($orderBy)->limit($limit)->page($page)->select();
            } else {
                $taskList = $mtask->where($cond)->order($orderBy)->limit($limit)->page($page)->select();
                if ($taskList) {
                    $tgIds = array();
                    foreach ($taskList as $task) {
                        $tgIds[$task['tg_id']] = $task['tg_id'];
                    }
                    $tkTgList = $mtaskgroup->where(array('id' => array('in', $tgIds)))->select(array('index' => 'id,name'));
                    $this->assign('tgList', $tkTgList);
                }
            }
            $count = $mtask->where($cond)->count();
            if ($count > $limit) {
                $pageCount = ceil($count / $limit);
                $cpage = new \Think\Page($count, $limit);
                if ($search) {
                    $cpage->parameter = array_merge($cpage->parameter, $search);
                }
                $pagenav = bootstrap_pages($cpage->show());
                $this->assign('pagenav', $pagenav);
            }
            $this->assign('taskList', $taskList);
            $tgSelect = $mtaskgroup->getLevelSelect();
            $tgSelect = preg_replace('/<select[^<>]*>/i', "$0<option value=''>" . L('all') . "</option>", $tgSelect);
            $this->assign('tgSelect', $tgSelect);
        }
        $showChange = $show == 'list' ? 'folder' : 'list';
        $GLOBALS['content_header'] = L('task_list') . ' <small><a href="' . U('Task/list?show=' . $showChange) . '">' . L('task_change_' . $showChange) . '</a></small>';
        $GLOBALS['breadcrumb'] = breadcrumb(array(array('url' => U('Task/list'), 'title' => L('task_list'))));
        $this->display('list_' . $show);
    }

    public function openListAction()
    {
        $tgid = I('tg_id', 0, 'intval');
        $mtaskgroup = D('taskgroup');
        $mtask = D('Task');
        $subTgList = $mtaskgroup->where(array('parent_id' => $tgid))->order('`sort` desc')->select();
        $taskList = $mtask->where(array('tg_id' => $tgid))->order('`sort` desc')->select();
        if (!empty($subTgList) || !empty($taskList)) {
            foreach ($taskList as $tk => $tv) {
                $tv['module'] = L('task_module_' . $tv['module']);
                $tv['addtime'] = date('Y-m-d', $tv['addtime']);
                $tv['caijitime'] = $tv['caijitime'] > 0 ? date('Y-m-d H:i', $tv['caijitime']) : '无';
                $taskList[$tk] = $tv;
            }
            $this->success(array('tgList' => $subTgList, 'taskList' => $taskList));
        } else {
            $this->error();
        }
    }

    public function addAction()
    {
        $mtask = D('Task');
        if (IS_POST) {
            $newData = $mtask->create();
            if (empty($newData)) {
                $this->error($mtask->getError());
            }
            $newData['config']['num'] = intval($newData['config']['num']);
            $newData['config'] = serialize($newData['config']);
            $newData['addtime'] = NOW_TIME;
            $tid = $mtask->add($newData);
            if ($tid > 0) {
                $taskData = $mtask->getById($tid);
                $ruleId = I('rule_id');
                if (!empty($taskData) && !empty($ruleId)) {
                    $this->_import_rule($taskData, $ruleId);
                }
                $this->success(L('op_success'), I('referer', '', 'trim') ? I('referer', '', 'trim') : U('Task/edit?id=' . $tid));
            } else {
                $this->error(L('op_failed'));
            }
        } else {
            $mtaskgroup = D('Taskgroup');
            $tgSelect = $mtaskgroup->getLevelSelect();
            $GLOBALS['content_header'] = L('task_add');
            $GLOBALS['breadcrumb'] = breadcrumb(array(array('url' => U('Task/list'), 'title' => L('task_list')), L('task_add')));
            $this->assign('tgSelect', $tgSelect);
            $this->display(IS_AJAX ? 'add_ajax' : 'add');
        }
    }

    public function editAction()
    {
        $id = I('id', 0, 'intval');
        $mtask = D('Task');
        $taskData = $mtask->getById($id);
        if (empty($id)) {
            $this->error(L('task_error_null_id'));
        }
        if (empty($taskData)) {
            $this->error(L('task_error_empty_task'));
        }
        if (IS_POST) {
            $newData = $mtask->create();
            if (empty($newData)) {
                $this->error($mtask->getError());
            }
            $newData['config']['num'] = intval($newData['config']['num']);
            $newData['config'] = serialize($newData['config']);
            if ($taskData['name'] != $newData['name']) {
                if ($mtask->where(array('name' => $newData['name']))->count() > 0) {
                    $this->error(L('task_error_has_name'));
                }
            }
            unset($newData['id']);
            if ($mtask->where('id=%d', $taskData['id'])->save($newData) >= 0) {
                $taskData = $mtask->getById($taskData['id']);
                $ruleId = I('rule_id');
                if (!empty($taskData) && !empty($ruleId)) {
                    $this->_import_rule($taskData, $ruleId);
                }
                $this->success(L('op_success'), U('Task/edit?id=' . $taskData['id']));
            } else {
                $this->error(L('op_failed'));
            }
        } else {
            $taskData['config'] = unserialize($taskData['config']);
            $taskData['config'] = is_array($taskData['config']) ? $taskData['config'] : array();
            $mtaskgroup = D('Taskgroup');
            $tgSelect = $mtaskgroup->getLevelSelect();
            $GLOBALS['content_header'] = L('task_edit');
            $GLOBALS['breadcrumb'] = breadcrumb(array(array('url' => U('Task/list'), 'title' => L('task_list')), L('task_edit')));
            $this->assign('tgSelect', $tgSelect);
            $this->assign('taskData', $taskData);
            $this->display(IS_AJAX ? 'add_ajax' : 'add');
        }
    }

    public function _import_rule($taskData, $ruleId)
    {
        $mtask = D('Task');
        $mrule = D('Rule');
        $mcoll = D('Collector');
        list($ruleType, $ruleId) = explode(':', $ruleId);
        $ruleId = intval($ruleId);
        $ruleType = strtolower($ruleType);
        if (!empty($taskData)) {
            $name = null;
            $module = null;
            $config = null;
            if ('rule' == $ruleType) {
                $ruleData = $mrule->getById($ruleId);
            } elseif ('collector' == $ruleType) {
                $ruleData = $mcoll->getById($ruleId);
            } elseif ('file' == $ruleType) {
                $file = $_FILES['rule_file'];
                $fileTxt = file_get_contents($file['tmp_name']);
                if (preg_match('/\/\*skycaiji-collector-start\*\/(?P<coll>[\s\S]+?)\/\*skycaiji-collector-end\*\//i', $fileTxt, $ruleMatch)) {
                    $ruleData = unserialize(base64_decode(trim($ruleMatch['coll'])));
                }
            }
            if (!empty($ruleData)) {
                $name = $ruleData['name'];
                $module = $ruleData['module'];
                $config = $ruleData['config'];
            }
            $referer = I('referer', '', 'trim') ? I('referer', '', 'trim') : U('Task/edit?id=' . $taskData['id']);
            if (empty($module) || (strcasecmp($module, $taskData['module']) !== 0)) {
                $this->error('导入的规则模块错误', $referer);
            }
            if (empty($config)) {
                $this->error('导入的规则为空', $referer);
            }
            $collData = $mcoll->where(array('task_id' => $taskData['id'], 'module' => $module))->find();
            $newColl = array('name' => $name, 'module' => $module, 'task_id' => $taskData['id'], 'config' => $config, 'uptime' => NOW_TIME);
            if (empty($collData)) {
                $mcoll->add_new($newColl);
            } else {
                $mcoll->edit_by_id($collData['id'], $newColl);
            }
        }
    }

    public function opAction()
    {
        $id = I('id', 0, 'intval');
        $op = I('get.op');
        if (empty($op)) {
            $op = I('post.op');
        }
        $ops = array('item' => array('delete', 'auto'), 'list' => array('saveall'));
        if (!in_array($op, $ops['item']) && !in_array($op, $ops['list'])) {
            $this->error(L('invalid_op'));
        }
        $mtask = D('Task');
        if (in_array($op, $ops['item'])) {
            $taskData = $mtask->getById($id);
            if (empty($taskData)) {
                $this->error(L('empty_data'));
            }
        }
        $this->assign('op', $op);
        if ($op == 'delete') {
            $mtask->where(array('id' => $id))->delete();
            $this->success(L('delete_success'));
        } elseif ($op == 'auto') {
            $auto = min(1, I('auto', 0, 'intval'));
            $mtask->where(array('id' => $taskData['id']))->save(array('auto' => $auto));
            $this->success(L('op_success'));
        } elseif ($op == 'saveall') {
            $newsort = I('newsort');
            if (is_array($newsort) && count($newsort) > 0) {
                foreach ($newsort as $key => $val) {
                    $mtask->where(array('id' => intval($key)))->save(array('sort' => intval($val)));
                }
            }
            $this->success(L('op_success'), U('Task/list?show=' . I('show')));
        }
    }

    public function collectAction()
    {
        $taskId = I('id', 0, 'intval');
        $this->_collect($taskId);
    }

    public function _collect($taskId)
    {
        static $setted_timeout = null;
        if (!isset($setted_timeout)) {
            if ($GLOBALS['config']['caiji']['timeout'] > 0) {
                set_time_limit(60 * $GLOBALS['config']['caiji']['timeout']);
            } else {
                set_time_limit(0);
            }
            $setted_timeout = 1;
        }
        $mtask = D('Task');
        $taskData = $mtask->getById($taskId);
        if (empty($taskData)) {
            $this->echo_msg(L('task_error_empty_task'));
            exit();
        }
        if (empty($taskData['module'])) {
            $this->echo_msg(L('task_error_null_module'));
            exit();
        }
        if (!in_array($taskData['module'], C('ALLOW_COLL_MODULES'))) {
            $this->echo_msg(L('coll_error_invalid_module'));
            exit();
        }
        $taskData['config'] = unserialize($taskData['config']);
        D('Task')->loadConfig($taskData['config']);
        $mcoll = D('Collector');
        $collData = $mcoll->where(array('task_id' => $taskData['id'], 'module' => $taskData['module']))->find();
        if (empty($collData)) {
            $this->echo_msg(L('coll_error_empty_coll'));
            exit();
        }
        $mrele = D('Release');
        $releData = $mrele->where(array('task_id' => $taskData['id']))->find();
        if (empty($releData)) {
            $this->echo_msg(L('rele_error_empty_rele'));
            exit();
        }
        $mtask->where(array('id' => $taskData['id']))->save(array('caijitime' => NOW_TIME));
        $acoll = a_c($collData['module']);
        $acoll->init($collData);
        $arele = A('Admin/R' . strtolower($releData['module']), 'Event');
        $arele->init($releData);
        $GLOBALS['real_time_release'] =& $arele;
        if ('api' == $releData['module']) {
            $GLOBALS['config']['caiji']['real_time'] = 0;
            $cacheApiData = $arele->get_cache_fields();
            if ($cacheApiData !== false) {
                $this->ajaxReturn($cacheApiData);
            }
        }
        $all_field_list = array();
        $caijiNum = intval($GLOBALS['config']['caiji']['num']);
        $taskNum = intval($taskData['config']['num']);
        if ($taskNum <= 0 || ($caijiNum > 0 && $taskNum > $caijiNum)) {
            $taskNum = $caijiNum;
        }
        $caijiLimit = false;
        if ($taskNum > 0) {
            $caijiLimit = true;
        }
        if ($caijiLimit) {
            while ($taskNum > 0) {
                $field_list = $acoll->collect($taskNum);
                if ($field_list == 'completed') {
                    break;
                } elseif (is_array($field_list) && !empty($field_list)) {
                    $all_field_list = array_merge($all_field_list, $field_list);
                    $taskNum -= count($field_list);
                }
                if ($taskNum > 0) {
                    $this->echo_msg('采集到' . count($field_list) . '条数据，还差' . $taskNum . '条', 'orange');
                }
            }
        } else {
            do {
                $field_list = $acoll->collect($taskNum);
                if (is_array($field_list) && !empty($field_list)) {
                    $all_field_list = array_merge($all_field_list, $field_list);
                }
            } while ($field_list != 'completed');
        }
        if (empty($all_field_list)) {
            $this->echo_msg('没有采集到数据', 'orange');
        } else {
            $this->echo_msg('采集到' . count($all_field_list) . '条数据', 'green');
            if (empty($GLOBALS['config']['caiji']['real_time'])) {
                $addedNum = $arele->export($all_field_list);
                $this->echo_msg('成功发布' . $addedNum . '条数据', 'green');
            }
        }
    }
}