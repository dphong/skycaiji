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

use Install\Event\UpgradeDbEvent;

if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

class ApiController extends BaseController
{
    public function taskAction()
    {
        define('CLOSE_ECHO_MSG', 1);
        $taskId = I('id', 0, 'intval');
        $apiurl = I('get.apiurl');
        $releData = D('Release')->where(array('task_id' => $taskId))->find();
        $releData['config'] = unserialize($releData['config']);
        if ($apiurl != $releData['config']['api']['url']) {
            exit('api地址错误！');
        }
        header('Content-type:text/json');
        A('Admin/Task')->_collect($taskId);
    }

    public function collectAction()
    {
        define('IS_COLLECTING', 1);
        if (!session('user_id')) {
            define('CLOSE_ECHO_MSG', true);
        }
        ignore_user_abort(true);
        if ($GLOBALS['config']['caiji']['timeout'] > 0) {
            set_time_limit(60 * $GLOBALS['config']['caiji']['timeout']);
        } else {
            set_time_limit(0);
        }
        if (empty($GLOBALS['config']['caiji']['auto'])) {
            $this->error('请先开启自动采集', U('Admin/Setting/caiji'));
        }
        $lastCollectTime = F('last_collect_time');
        if ($GLOBALS['config']['caiji']['interval'] > 0) {
            $waitTime = (60 * $GLOBALS['config']['caiji']['interval']) - abs(time() - $lastCollectTime);
            if ($waitTime > 0) {
                $this->error('再次采集需等待' . (($waitTime < 60) ? ($waitTime . '秒') : (sprintf("%.2f", $waitTime / 60) . '分钟')), U('Admin/Api/collect'), $waitTime);
            }
        }
        $taskList = D('Task')->alias('t')->join(D('Collector')->getTableName() . ' c on t.id=c.task_id')->field('t.*')->where("t.auto=1 and t.module='pattern'")->order('t.caijitime asc')->select();
        if (empty($taskList)) {
            $this->error('没有可自动采集的任务');
        }
        F('last_collect_time', time());
        $mtask = D('Task');
        $mcoll = D('Collector');
        $mrele = D('Release');
        $caijiNum = intval($GLOBALS['config']['caiji']['num']);
        $caijiLimit = false;
        if ($caijiNum > 0) {
            $caijiLimit = true;
        }
        foreach ($taskList as $taskData) {
            $mtask->where(array('id' => $taskData['id']))->save(array('caijitime' => time()));
            $collData = $mcoll->where(array('task_id' => $taskData['id'], 'module' => $taskData['module']))->find();
            $releData = $mrele->where(array('task_id' => $taskData['id']))->find();
            if (empty($collData) || empty($releData)) {
                continue;
            }
            if ($releData['module'] == 'api') {
                continue;
            }
            $taskData['config'] = unserialize($taskData['config']);
            D('Task')->loadConfig($taskData['config']);
            $collEvent = 'Admin\\Event\\C' . strtolower($collData['module']) . 'Event';
            $acoll = new $collEvent();
            $acoll->init($collData);
            $releEvent = 'Admin\\Event\\R' . strtolower($releData['module']) . 'Event';
            $arele = new $releEvent();
            $arele->init($releData);
            $GLOBALS['real_time_release'] =& $arele;
            $this->echo_msg('<div style="background:#efefef;padding:5px;margin:5px 0;text-align:center;">正在执行任务：' . $taskData['name'] . '</div>', 'black');
            $all_field_list = array();
            $taskNum = intval($taskData['config']['num']);
            if ($taskNum <= 0 || ($caijiLimit && $taskNum > $caijiNum)) {
                $taskNum = $caijiNum;
            }
            if ($taskNum > 0) {
                while ($taskNum > 0) {
                    $field_list = $acoll->collect($taskNum);
                    if ($field_list == 'completed') {
                        break;
                    } elseif (is_array($field_list) && !empty($field_list)) {
                        $all_field_list = array_merge($all_field_list, $field_list);
                        $taskNum -= count($field_list);
                        $caijiNum -= count($field_list);
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
                $this->echo_msg('任务：' . $taskData['name'] . ' 没有采集到数据', 'orange');
            } else {
                $this->echo_msg('任务：' . $taskData['name'] . ' 采集到' . count($all_field_list) . '条数据', 'green');
                if (empty($GLOBALS['config']['caiji']['real_time'])) {
                    $addedNum = $arele->export($all_field_list);
                    $this->echo_msg('成功发布' . $addedNum . '条数据', 'green');
                }
            }
            $this->echo_msg('<div style="background:#efefef;padding:5px;margin:5px 0;text-align:center;color:green;">任务：' . $taskData['name'] . ' 执行完毕</div>', 'green');
            if ($caijiLimit) {
                if ($caijiNum > 0) {
                    $this->echo_msg('还差' . $caijiNum . '条数据', 'orange');
                } else {
                    break;
                }
            }
        }
        $this->echo_msg('所有任务执行完毕！', 'green');
    }

    public function aaaAction()
    {
        $aaa = new UpgradeDbEvent();
        $aaa->upgrade_db_to_1_3();
    }
}