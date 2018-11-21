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

namespace Admin\Event;
if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

class RdiyEvent extends ReleaseEvent
{
    public function setConfig($config)
    {
        $diy = I('diy/a');
        if (!in_array($diy['type'], array('app', 'code'))) {
            $this->error('类型错误');
        }
        if ($diy['type'] == 'app') {
            $diy['app'] = strtolower(trim($diy['app'], '\/\\'));
            if (empty($diy['app'])) {
                $this->error('请输入插件名称');
            }
            if (in_array($diy['app'], array('base', 'code'))) {
                $this->error($diy['app'] . '为系统保留名称，不能使用');
            }
            if (!preg_match('/^[a-z][a-z0-9]+$/i', $diy['app'])) {
                $this->error('插件名称必须以字母开头且由字母或数字组成');
            }
        } elseif ($diy['type'] == 'code') {
            if (empty($diy['code'])) {
                $this->error('请输入PHP代码');
            }
        }
        $config['diy'] = $diy;
        return $config;
    }

    public function export($collFieldsList, $options = null)
    {
        try {
            if ($this->config['diy']['type'] == 'app') {
                $releDiy = A('Release/' . ucfirst(strtolower($this->config['diy']['app'])), 'Diy');
            } elseif ($this->config['diy']['type'] == 'code') {
                $releDiy = A('Release/Code', 'Diy');
            }
            if (empty($releDiy)) {
                $this->echo_msg('没有该插件');
                exit();
            }
            $releDiy->init($this->release);
        } catch (\Exception $ex) {
            $this->echo_msg($ex->getMessage());
            exit();
        }
        $addedNum = 0;
        foreach ($collFieldsList as $collFieldsKey => $collFields) {
            $releDiy->db->startTrans();
            $errorMsg = false;
            $contUrl = $collFields['url'];
            try {
                $return = $releDiy->runExport($contUrl, $collFields['fields']);
                if (empty($return)) {
                    continue;
                }
            } catch (\Exception $ex) {
                $return = array();
                $errorMsg = $ex->getMessage();
                $this->echo_msg($ex->getMessage());
                break;
            }
            $return = empty($return) ? array('id' => 0) : $return;
            if (!empty($errorMsg)) {
                $return['error'] = $errorMsg;
            }
            $returnData = array();
            if (!empty($return['error'])) {
                $releDiy->db->rollback();
                $returnData = array('id' => 0, 'error' => $errorMsg);
            } else {
                $releDiy->db->commit();
                $returnData = $return;
                unset($returnData['error']);
                if ($returnData['id'] > 0) {
                    $addedNum++;
                } else {
                    $returnData['error'] = '数据插入失败';
                }
            }
            $this->record_collected($contUrl, $returnData, $this->release, $collFields['title']);
            unset($collFieldsList[$collFieldsKey]['fields']);
        }
        return $addedNum;
    }
} ?>