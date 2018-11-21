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

use Admin\Model\DbCommonModel;
use Think\Db;

if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

class RdbEvent extends ReleaseEvent
{
    protected $db_conn_list = array();

    public function setConfig($config)
    {
        $db = I('db/a', '', 'trim');
        foreach ($db as $k => $v) {
            if (empty($v) && 'pwd' != $k) {
                $this->error(L('error_null_input', array('str' => L('rele_db_' . $k))));
            }
        }
        $config['db'] = $db;
        $config['db_table'] = I('db_table/a', '', 'trim');
        foreach ($config['db_table']['field'] as $tbName => $tbFields) {
            foreach ($tbFields as $tbField => $fieldVal) {
                if (empty($fieldVal)) {
                    unset($config['db_table']['field'][$tbName][$tbField]);
                    unset($config['db_table']['custom'][$tbName][$tbField]);
                    continue;
                }
            }
        }
        return $config;
    }

    public function export($collFieldsList, $options = null)
    {
        $db_config = $this->get_db_config($this->config['db']);
        $db_key = md5(addslashes($db_config));
        if (empty($this->db_conn_list[$db_key])) {
            $dbClass = new DbCommonModel('', null, $db_config);
            $this->db_conn_list[$db_key] = $dbClass;
        } else {
            $dbClass = $this->db_conn_list[$db_key];
        }
        $addedNum = 0;
        $dbCharset = strtolower($db_config['db_charset']);
        if (empty($dbCharset) || $dbCharset == 'utf-8' || $dbCharset == 'utf8') {
            $dbCharset = null;
        }
        foreach ($collFieldsList as $collFieldsKey => $collFields) {
            $dbClass->startTrans();
            $contUrl = $collFields['url'];
            $collFields = $collFields['fields'];
            $tableFields = array();
            foreach ($this->config['db_table']['field'] as $tbName => $tbFields) {
                foreach ($tbFields as $tbField => $fieldVal) {
                    if (empty($fieldVal)) {
                        unset($tbFields[$tbField]);
                        continue;
                    }
                    if (strcasecmp('custom:', $fieldVal) == 0) {
                        $fieldVal = $this->config['db_table']['custom'][$tbName][$tbField];
                    } elseif (preg_match('/^field\:(.+)$/ui', $fieldVal, $collField)) {
                        $fieldVal = $this->get_field_val($collFields[$collField[1]]);
                        $fieldVal = is_null($fieldVal) ? '' : $fieldVal;
                    }
                    if (!empty($dbCharset)) {
                        $fieldVal = $this->utf8_to_charset($dbCharset, $fieldVal);
                    }
                    $tbFields[$tbField] = $fieldVal;
                }
                $tableFields[$tbName] = $tbFields;
            }
            if (!empty($tableFields)) {
                if ('oracle' == $db_config['db_type']) {
                    $pdoOracle = new \PDO($db_config['db_dsn'], $db_config['db_user'], $db_config['db_pwd'], array());
                    $pdoOracle->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                }
                $errorMsg = false;
                $autoidList = array();
                foreach ($tableFields as $table => $fields) {
                    $table = strtolower($table);
                    foreach ($fields as $k => $v) {
                        if (preg_match('/^auto_id\@([^\s]+)$/i', $v, $autoidTbName)) {
                            $autoidTbName = trim($autoidTbName[1]);
                            $autoidTbName = strtolower($autoidTbName);
                            $fields[$k] = $autoidList[$autoidTbName];
                        }
                    }
                    try {
                        if ('oracle' == $db_config['db_type']) {
                            $insertSql = 'insert into ' . $table . ' ';
                            $insertKeys = array();
                            $insertVals = array();
                            $sequenceName = '';
                            foreach ($fields as $k => $v) {
                                if (preg_match('/^sequence\@([^\s]+)$/i', $v, $m_sequence)) {
                                    $sequenceName = $m_sequence[1];
                                    continue;
                                }
                                $insertKeys[] = $k;
                                $insertVals[] = "'" . str_replace("'", "''", $v) . "'";
                            }
                            $insertSql .= '(' . implode(',', $insertKeys) . ') values (' . implode(',', $insertVals) . ')';
                            if ($pdoOracle->exec($insertSql)) {
                                if (!empty($sequenceName)) {
                                    $autoId = $pdoOracle->query("select {$sequenceName}.CURRVAL as id FROM DUAL");
                                    if ($autoId) {
                                        $autoId = $autoId->fetch();
                                        $autoidList[$table] = $autoId[0];
                                    }
                                }
                                if (empty($autoidList[$table])) {
                                    $autoidList[$table] = 1;
                                }
                            } else {
                                $autoidList[$table] = 0;
                            }
                        } else {
                            $autoidList[$table] = $dbClass->table($table)->add($fields);
                        }
                    } catch (\Exception $ex) {
                        $errorMsg = $ex->getMessage();
                        $this->echo_msg($errorMsg ? $errorMsg : $dbClass->getDbError());
                        $errorMsg = !empty($errorMsg) ? $errorMsg : ($table . '表入库失败');
                        break;
                    }
                    if ($autoidList[$table] <= 0) {
                        break;
                    }
                }
                $returnData = array('id' => 0);
                if (!empty($errorMsg)) {
                    $dbClass->rollback();
                    $returnData['error'] = $errorMsg;
                } else {
                    $dbClass->commit();
                    reset($autoidList);
                    list($firstTable, $firstId) = each($autoidList);
                    $firstId = intval($firstId);
                    if ($firstId > 0) {
                        $addedNum++;
                        $returnData['id'] = $firstId;
                        $returnData['target'] = "{$db_config['db_type']}:{$db_config['db_name']}@table:{$firstTable}@id:{$firstId}";
                    } else {
                        $returnData['error'] = '数据插入失败';
                    }
                }
                $this->record_collected($contUrl, $returnData, $this->release, $collFields['title']);
            }
            unset($collFieldsList[$collFieldsKey]['fields']);
        }
        return $addedNum;
    }

    public function get_db_config($config_db)
    {
        $db_config = array('db_type' => strtolower($config_db['type']), 'db_user' => $config_db['user'], 'db_pwd' => $config_db['pwd'], 'db_host' => $config_db['host'], 'db_port' => $config_db['port'], 'db_charset' => $config_db['charset'], 'db_name' => $config_db['name']);
        if (strcasecmp($db_config['db_charset'], 'utf-8') === 0) {
            $db_config['db_charset'] = 'utf8';
        }
        if ('mysqli' == $db_config['db_type']) {
            $db_config['db_type'] = 'mysql';
        } elseif ('oracle' == $db_config['db_type']) {
            $db_config['db_dsn'] = "oci:host={$db_config['db_host']};dbname={$db_config['db_name']};charset={$db_config['db_charset']}";
        }
        return $db_config;
    }
} ?>