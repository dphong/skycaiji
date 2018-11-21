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

use Think\Model;
use Think\Db;

class BaseModel extends Model
{
    public $db = null;
    public $_db = array();
    public $connection = '';

    public function __construct($name = '', $tablePrefix = '', $connection = '')
    {
        C('DB_DEBUG', true);
        parent::__construct($name, $tablePrefix, $connection);
    }

    public function db($linkNum = '', $config = '', $force = false)
    {
        if (!is_collecting()) {
            return parent::db($linkNum, $config, $force);
        } else {
            if ('' === $linkNum && $this->db) {
                return $this->db;
            }
            if (!isset($this->_db[$linkNum]) || $force) {
                if (empty($GLOBALS['collecting_db']) || !is_array($GLOBALS['collecting_db'])) {
                    $GLOBALS['collecting_db'] = array();
                    $GLOBALS['collecting_db_config'] = array();
                }
                if (!empty($config) && is_string($config) && false === strpos($config, '/')) {
                    $config = C($config);
                }
                if (empty($config)) {
                    $config = C();
                }
                $dbMd5 = md5(serialize($config));
                $GLOBALS['collecting_db_config'][$dbMd5] = $config;
                if (is_array($config)) {
                    $config['db_debug'] = true;
                    $config['_collecting_now_time'] = time();
                }
                $GLOBALS['collecting_db'][$dbMd5] = Db::getInstance($config);
                $this->_db[$linkNum] =& $GLOBALS['collecting_db'][$dbMd5];
            } elseif (NULL === $config) {
                return;
            }
            $this->db = &$this->_db[$linkNum];
            parent::_after_db();
            if (!empty(parent::$name) && parent::$autoCheckFields) {
                parent::_checkTableInfo();
            }
            return $this;
        }
    }

    public static function _reset_collecting_db()
    {
        if (is_collecting() && !empty($GLOBALS['collecting_db'])) {
            foreach ($GLOBALS['collecting_db'] as $dbMd5 => $v) {
                $config = $GLOBALS['collecting_db_config'][$dbMd5];
                if (is_array($config)) {
                    $config['db_debug'] = true;
                    $config['_collecting_now_time'] = time();
                }
                $GLOBALS['collecting_db'][$dbMd5] = Db::getInstance($config);
            }
        }
    }

    public function _exception_handler($method, $argNum = 0, $args = null)
    {
        if (!is_collecting()) {
            if ($argNum > 0) {
                return call_user_func_array(array(parent, $method), $args);
            } else {
                return call_user_func(array(parent, $method));
            }
        } else {
            try {
                if ($argNum > 0) {
                    return call_user_func_array(array(parent, $method), $args);
                } else {
                    return call_user_func(array(parent, $method));
                }
            } catch (\Exception $ex) {
                if (stripos($ex->getMessage(), 'server has gone away') !== false) {
                    self::_reset_collecting_db();
                    if ($argNum > 0) {
                        return call_user_func_array(array(parent, $method), $args);
                    } else {
                        return call_user_func(array(parent, $method));
                    }
                } else {
                    throw new \Exception();
                }
            }
        }
    }

    public function select($options = array())
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function flush()
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function add($data = '', $options = array(), $replace = false)
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function selectAdd($fields = '', $table = '', $options = array())
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function save($data = '', $options = array())
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function delete($options = array())
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function find($options = array())
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function getField($field, $sepa = null)
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function procedure($sql, $parse = false)
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function query($sql, $parse = false)
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function execute($sql, $parse = false)
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    protected function parseSql($sql, $parse)
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function startTrans()
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function commit()
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function rollback()
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function getDbError()
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function getLastInsID()
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function getLastSql()
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function getDbFields()
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }

    public function where($where, $parse = null)
    {
        return $this->_exception_handler(__FUNCTION__, func_num_args(), func_get_args());
    }
} ?>