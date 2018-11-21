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

class TaskgroupController extends BaseController
{
    public function listAction()
    {
        $page = I('p', 1, 'intval');
        $page = max(1, $page);
        $search['parent_id'] = I('parent_id', 0, 'intval');
        $search['name'] = I('name');
        $mtaskgroup = D('Taskgroup');
        $cond = array();
        if ($search['parent_id'] > 0) {
            $cond['parent_id'] = $search['parent_id'];
        }
        if (!empty($search['name'])) {
            $cond['name'] = array('like', '%' . addslashes($search['name']) . '%');
        }
        $this->assign('search', $search);
        $limit = 20;
        if ($cond) {
            $count = $mtaskgroup->where($cond)->count();
            if ($count > 0) {
                $parentList = $mtaskgroup->where($cond)->order('`sort` desc')->limit($limit)->page($page)->select();
            }
        } else {
            $cond = array('parent_id' => 0);
            $count = $mtaskgroup->where($cond)->count();
            if ($count > 0) {
                $parentList = $mtaskgroup->where($cond)->order('`sort` desc')->limit($limit)->page($page)->select();
                $parentIds = array();
                foreach ($parentList as $item) {
                    $parentIds[$item['id']] = $item['id'];
                }
                $subList1 = $mtaskgroup->where(array('parent_id' => array('in', $parentIds)))->order('`sort` desc')->select();
                $subList = array();
                foreach ($subList1 as $item) {
                    $subList[$item['parent_id']][$item['id']] = $item;
                }
                unset($subList1);
            }
        }
        if ($count > $limit) {
            $pageCount = ceil($count / $limit);
            $cpage = new \Think\Page($count, $limit);
            if ($search) {
                $cpage->parameter = array_merge($cpage->parameter, $search);
            }
            $pagenav = bootstrap_pages($cpage->show());
            $this->assign('pagenav', $pagenav);
        }
        $this->assign('parentList', $parentList);
        $this->assign('subList', $subList);
        $parentTgList = $mtaskgroup->where(array('parent_id' => 0))->order('`sort` desc')->select(array('index' => 'id,name'));
        $this->assign('parentTgList', $parentTgList);
        $GLOBALS['content_header'] = L('taskgroup_list');
        $GLOBALS['breadcrumb'] = breadcrumb(array(array('url' => U('Taskgroup/list'), 'title' => L('taskgroup_list'))));
        $this->display();
    }

    public function addAction()
    {
        $mtaskgroup = D('Taskgroup');
        if (IS_POST) {
            $newData = $mtaskgroup->create();
            if (empty($newData)) {
                $this->error($mtaskgroup->getError());
            }
            $tgid = $mtaskgroup->add($newData);
            if ($tgid > 0) {
                $this->success(L('op_success'), I('referer', '', 'trim') ? I('referer', '', 'trim') : U('Taskgroup/edit?id=' . $tgid));
            } else {
                $this->error(L('op_failed'));
            }
        } else {
            $parentTgList = $mtaskgroup->where(array('parent_id' => 0))->order('`sort` desc')->select(array('index' => 'id,name'));
            $this->assign('parentTgList', $parentTgList);
            $GLOBALS['content_header'] = L('taskgroup_add');
            $GLOBALS['breadcrumb'] = breadcrumb(array(array('url' => U('Taskgroup/list'), 'title' => L('taskgroup_list')), L('taskgroup_add')));
            $this->display(IS_AJAX ? 'add_ajax' : 'add');
        }
    }

    public function editAction()
    {
        $mtaskgroup = D('Taskgroup');
        $id = I('id', 0, 'intval');
        $tgData = $mtaskgroup->getById($id);
        if (empty($tgData)) {
            $this->error(L('tg_none'));
        }
        if (IS_POST) {
            $newData = $mtaskgroup->create();
            if (empty($newData)) {
                $this->error($mtaskgroup->getError());
            }
            if ($tgData['name'] != $newData['name']) {
                if ($mtaskgroup->where(array('name' => $newData['name']))->count() > 0) {
                    $this->error(L('tg_error_has_name'));
                }
            }
            unset($newData['id']);
            if ($newData['parent_id'] > 0) {
                $subCount = $mtaskgroup->where(array('parent_id' => $tgData['id']))->count();
                if ($subCount > 0) {
                    $this->error(L('tg_is_parent'));
                }
            }
            if ($newData['parent_id'] == $tgData['id']) {
                unset($newData['parent_id']);
            }
            if ($mtaskgroup->where('id=%d', $tgData['id'])->save($newData) >= 0) {
                $this->success(L('op_success'), U('Taskgroup/edit?id=' . $tgData['id']));
            } else {
                $this->error(L('op_failed'));
            }
        } else {
            $parentTgList = $mtaskgroup->where(array('parent_id' => 0))->order('`sort` desc')->select(array('index' => 'id,name'));
            $this->assign('parentTgList', $parentTgList);
            $this->assign('tgData', $tgData);
            $GLOBALS['content_header'] = L('taskgroup_edit');
            $GLOBALS['breadcrumb'] = breadcrumb(array(array('url' => U('Taskgroup/list'), 'title' => L('taskgroup_list')), L('taskgroup_edit')));
            $this->display(IS_AJAX ? 'add_ajax' : 'add');
        }
    }

    public function opAction()
    {
        $id = I('id', 0, 'intval');
        $op = I('get.op');
        if (empty($op)) {
            $op = I('post.op');
        }
        $ops = array('item' => array('delete', 'move'), 'list' => array('deleteall', 'saveall'));
        if (!in_array($op, $ops['item']) && !in_array($op, $ops['list'])) {
            $this->error(L('invalid_op'));
        }
        $mtaskgroup = D('Taskgroup');
        if (in_array($op, $ops['item'])) {
            $tgData = $mtaskgroup->getById($id);
            if (empty($tgData)) {
                $this->error(L('empty_data'));
            }
            $this->assign('tgData', $tgData);
        }
        $this->assign('op', $op);
        $mtask = D('Task');
        if ($op == 'delete') {
            if ($mtaskgroup->where(array('parent_id' => $tgData['id']))->count() > 0) {
                $this->error(L('tg_exist_sub'));
            } else {
                $mtaskgroup->where(array('id' => $id))->delete();
                $mtask->where(array('tg_id' => $id))->save(array('tg_id' => 0));
                $this->success(L('delete_success'));
            }
        } elseif ($op == 'move') {
            $parentTgList = $mtaskgroup->where(array('parent_id' => 0))->select(array('index' => 'id,name'));
            if (IS_POST) {
                $parent_id = I('parent_id', 0, 'intval');
                if ($parent_id > 0 && $parent_id != $tgData['parent_id']) {
                    $subCount = $mtaskgroup->where(array('parent_id' => $tgData['id']))->count();
                    if ($subCount > 0) {
                        $this->error(L('tg_is_parent'));
                    }
                }
                if ($tgData['id'] != $parent_id) {
                    $mtaskgroup->where('id=%d', $tgData['id'])->save(array('parent_id' => $parent_id));
                }
                $this->success(L('op_success'), I('referer', '', 'trim'));
            } else {
                $this->assign('parentTgList', $parentTgList);
                $this->display();
            }
        } elseif ($op == 'deleteall') {
            $ids = I('ids');
            if (is_array($ids) && count($ids) > 0) {
                $list = $mtaskgroup->where(array('id' => array('in', $ids)))->select();
                $deleteIds = array();
                foreach ($list as $item) {
                    $subCount = $mtaskgroup->where(array('parent_id' => $item['id']))->count();
                    if ($subCount == 0) {
                        $deleteIds[$item['id']] = $item['id'];
                    } else {
                        $hasSub = true;
                    }
                }
                if ($deleteIds) {
                    $mtaskgroup->where(array('id' => array('in', $deleteIds)))->delete();
                    $mtask->where(array('tg_id' => array('in', $deleteIds)))->save(array('tg_id' => 0));
                }
            }
            $this->success(L($hasSub ? 'tg_deleteall_has_sub' : 'op_success'));
        } elseif ($op == 'saveall') {
            $ids = I('ids');
            $newsort = I('newsort');
            if (is_array($ids) && count($ids) > 0) {
                $ids = array_map('intval', $ids);
                $updateSql = ' UPDATE ' . $mtaskgroup->getTableName() . ' SET `sort` = CASE `id` ';
                foreach ($ids as $tgid) {
                    $updateSql .= sprintf(" WHEN %d THEN '%s' ", $tgid, intval($newsort[$tgid]));
                }
                $updateSql .= 'END WHERE `id` IN (' . implode(',', $ids) . ')';
                try {
                    $mtaskgroup->execute($updateSql);
                } catch (\Exception $ex) {
                    $this->error(L('op_failed'));
                }
            }
            $this->success(L('op_success'), U('list'));
        }
    }
}