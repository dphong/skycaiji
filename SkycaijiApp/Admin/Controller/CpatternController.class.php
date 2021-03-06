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

class CpatternController extends BaseController
{
    public function sourceAction()
    {
        $is_sub = I('sub');
        if (IS_POST && $is_sub) {
            $source = I('source/a', '', 'trim');
            if ($source['type'] == 'custom') {
                if (preg_match_all('/^\w+\:\/\/[^\r\n]+/im', $source['urls'], $urls)) {
                    $urls = array_unique($urls[0]);
                } else {
                    $this->error('请输入正确的网址');
                }
            } elseif ($source['type'] == 'batch') {
                if (!preg_match('/^\w+\:\/\/[^\r\n]+$/i', $source['url'])) {
                    $this->error('请输入正确的网址格式');
                }
                if (stripos($source['url'], cp_sign('match')) === false) {
                    $this->error('请在网址格式中添加 ' . cp_sign('match') . ' 才能批量生成网址！');
                }
                if (empty($source['param'])) {
                    $this->error('请选择参数类型');
                }
                $urls = array();
                $urlFmt = $source['url'];
                if ($source['param'] == 'num') {
                    $source['param_num_start'] = intval($source ['param_num_start']);
                    $source['param_num_end'] = intval($source ['param_num_end']);
                    $source['param_num_end'] = max($source ['param_num_start'], $source ['param_num_end']);
                    $source['param_num_inc'] = max(1, intval($source ['param_num_inc']));
                    $source['param_num_desc'] = $source['param_num_desc'] ? 1 : 0;
                    if ($source['param_num_desc']) {
                        for ($i = $source['param_num_end']; $i >= $source['param_num_start']; $i--) {
                            $urls[] = str_replace(cp_sign('match'), $source['param_num_start'] + ($i - $source['param_num_start']) * $source['param_num_inc'], $source['url']);
                        }
                    } else {
                        for ($i = $source['param_num_start']; $i <= $source['param_num_end']; $i++) {
                            $urls[] = str_replace(cp_sign('match'), $source['param_num_start'] + ($i - $source['param_num_start']) * $source['param_num_inc'], $source['url']);
                        }
                    }
                    $urlFmt = str_replace(cp_sign('match'), "{param:num,{$source['param_num_start']}\t{$source['param_num_end']}\t{$source['param_num_inc']}\t{$source['param_num_desc']}}", $urlFmt);
                } elseif ($source['param'] == 'letter') {
                    $letter_start = ord($source['param_letter_start']);
                    $letter_end = ord($source['param_letter_end']);
                    $letter_end = max($letter_start, $letter_end);
                    $source['param_letter_desc'] = $source['param_letter_desc'] ? 1 : 0;
                    if ($source['param_letter_desc']) {
                        for ($i = $letter_end; $i >= $letter_start; $i--) {
                            $urls[] = str_replace(cp_sign('match'), chr($i), $source['url']);
                        }
                    } else {
                        for ($i = $letter_start; $i <= $letter_end; $i++) {
                            $urls[] = str_replace(cp_sign('match'), chr($i), $source['url']);
                        }
                    }
                    $urlFmt = str_replace(cp_sign('match'), "{param:letter,{$source['param_letter_start']}\t{$source['param_letter_end']}\t{$source['param_letter_desc']}}", $urlFmt);
                } elseif ($source['param'] == 'custom') {
                    if (preg_match_all('/[^\r\n]+/', $source['param_custom'], $cusParams)) {
                        $cusParams = array_unique($cusParams[0]);
                        foreach ($cusParams as $cusParam) {
                            $urls[] = str_replace(cp_sign('match'), $cusParam, $source['url']);
                        }
                        $urlFmt = str_replace(cp_sign('match'), "{param:custom," . implode("\t", $cusParams) . "}", $urlFmt);
                    }
                }
            } elseif ($source['type'] == 'large') {
                if (preg_match_all('/^\w+\:\/\/[^\r\n]+/im', $source['large_urls'], $urls)) {
                    $urls = array_unique($urls[0]);
                } else {
                    $this->error('请输入正确的网址');
                }
            }
            if ($urls) {
                $urls = array_values($urls);
                $this->success(array('uid' => $source['uid'], 'url' => $urlFmt, 'urls' => $urls));
            } else {
                $this->error('未生成网址！');
            }
        } else {
            $url = I('url', '', 'trim');
            if ($url) {
                $source['uid'] = I('uid', '');
                if (preg_match('/\{param\:(\w+)\,([^\}]*)\}/i', $url, $param)) {
                    $source['url'] = preg_replace('/\{param\:(\w+)\,([^\}]*)\}/i', cp_sign('match'), $url);
                    $source['type'] = 'batch';
                    $source['param'] = strtolower($param[1]);
                    $param_val = explode("\t", $param[2]);
                    if ($source['param'] == 'num') {
                        $source['param_num_start'] = intval($param_val[0]);
                        $source['param_num_end'] = intval($param_val[1]);
                        $source['param_num_inc'] = intval($param_val[2]);
                        $source['param_num_desc'] = intval($param_val[3]);
                    } elseif ($source['param'] == 'letter') {
                        $source['param_letter_start'] = strtolower($param_val[0]);
                        $source['param_letter_end'] = strtolower($param_val[1]);
                        $source['param_letter_desc'] = intval($param_val[2]);
                    } elseif ($source['param'] == 'custom') {
                        $source['param_custom'] = implode("\r\n", $param_val);
                    }
                } elseif (preg_match('/[\r\n]/', $url)) {
                    $source['type'] = 'large';
                    $source['large_urls'] = $url;
                } else {
                    $source['type'] = 'custom';
                    $source['urls'] = $url;
                }
                $this->assign('source', $source);
            }
            $this->display();
        }
    }

    public function fieldAction()
    {
        if (IS_POST) {
            $objid = I('post.objid');
            $field = I('post.field/a', null, 'trim');
            if (empty($field['name'])) {
                $this->error('请输入字段名称');
            }
            $this->_check_name($field['name'], '字段名称');
            $field['module'] = strtolower($field['module']);
            switch ($field['module']) {
                case 'rule':
                    if (empty($field['rule'])) $this->error('规则不能为空！');
                    break;
                case 'auto':
                    if (empty($field['auto'])) $this->error('请选择自动获取的类型');
                    break;
                case 'xpath':
                    if (empty($field['xpath'])) $this->error('XPath规则不能为空！');
                    break;
                case 'json':
                    if (empty($field['json'])) $this->error('提取规则不能为空！');
                    break;
                case 'page':
                    if (empty($field['page'])) $this->error('请选择页面！');
                    if (empty($field['page_rule'])) $this->error('规则不能为空！');
                    break;
                case 'num':
                    $randNum = 0;
                    $field['num_start'] = intval($field['num_start']);
                    $field['num_end'] = intval($field['num_end']);
                    $field['num_end'] = max($field['num_start'], $field ['num_end']);
                    break;
                case 'words':
                    if (empty($field['words'])) $this->error('固定文字不能为空！');
                    break;
                case 'list':
                    if (empty($field['list'])) $this->error('随机抽取不能为空！');
                    break;
                case 'extract':
                    if (empty($field['extract'])) $this->error('请选择字段！');
                    break;
                case 'merge':
                    if (empty($field['merge'])) $this->error('字段组合不能为空！');
                    break;
            }
            $modules = array('rule' => array('rule', 'rule_multi', 'rule_multi_type', 'rule_multi_str', 'rule_merge'), 'auto' => 'auto', 'xpath' => array('xpath', 'xpath_multi', 'xpath_multi_type', 'xpath_multi_str', 'xpath_attr', 'xpath_attr_custom'), 'json' => array('json', 'json_arr', 'json_arr_implode'), 'page' => array('page', 'page_rule', 'page_rule_merge', 'page_rule_multi', 'page_rule_multi_str'), 'words' => 'words', 'num' => array('num_start', 'num_end'), 'time' => array('time_format', 'time_start', 'time_end', 'time_stamp'), 'list' => 'list', 'extract' => array('extract', 'extract_module', 'extract_rule', 'extract_xpath', 'extract_xpath_attr', 'extract_xpath_attr_custom', 'extract_json', 'extract_json_arr', 'extract_json_arr_implode'), 'merge' => 'merge');
            $returnField = array('name' => $field['name'], 'source' => $field['source'], 'module' => $field['module']);
            if (is_array($modules[$field['module']])) {
                foreach ($modules[$field['module']] as $mparam) {
                    $returnField[$mparam] = $field[$mparam];
                }
            } else {
                $returnField[$modules[$field['module']]] = $field[$modules[$field['module']]];
            }
            $this->success(array('field' => $returnField, 'objid' => $objid));
        } else {
            $field = I('field', '', 'url_b64decode');
            $objid = I('objid');
            $field = $field ? json_decode($field, true) : array();
            $field['time_format'] = $field['time_format'] ? $field['time_format'] : '[年]/[月]/[日] [时]:[分]';
            $this->assign('field', $field);
            $this->assign('objid', $objid);
            $this->display();
        }
    }

    public function processAction()
    {
        $type = I('get.type');
        $this->assign('type', $type);
        $op = I('get.op');
        if (empty($type)) {
            if (empty($op)) {
                $objid = I('objid');
                $process = I('process', '', 'url_b64decode');
                $process = $process ? json_decode($process, true) : '';
                $this->assign('objid', $objid);
                $this->assign('process', $process);
                $this->display();
            } elseif ($op == 'sub') {
                $process = I('process/a', null, 'trim');
                if (empty($process)) {
                    $process = '';
                }
                $objid = I('objid', '');
                $this->success(array('process' => $process, 'process_json' => empty($process) ? '' : json_encode($process), 'objid' => $objid));
            }
        } elseif ('common' == $type) {
            if (empty($op)) {
                $this->display();
            } elseif ($op == 'load') {
                $process = I('process/a', null, 'trim');
                $this->assign('process', $process);
                $this->display('process_load');
            }
        }
    }

    public function paging_fieldAction()
    {
        if (IS_POST) {
            $objid = I('post.objid');
            $pagingField = I('post.paging_field/a', null, 'trim');
            if (empty($pagingField['field'])) {
                $this->error('请选择字段');
            }
            $this->success(array('paging_field' => $pagingField, 'objid' => $objid));
        } else {
            $pagingField = I('paging_field', '', 'url_b64decode');
            $objid = I('objid');
            $pagingField = $pagingField ? json_decode($pagingField, true) : '';
            $this->assign('pagingField', $pagingField);
            $this->assign('objid', $objid);
            $this->display();
        }
    }

    public function level_urlAction()
    {
        if (IS_POST) {
            $objid = I('post.objid');
            $level_url = I('post.level_url/a', null, 'trim');
            if (empty($level_url['name'])) {
                $this->error('请输入名称');
            }
            $this->_check_name($level_url['name'], '多级名称');
            $this->success(array('level_url' => $level_url, 'objid' => $objid));
        } else {
            $level_url = I('level_url', '', 'url_b64decode');
            $objid = I('objid');
            $level_url = $level_url ? json_decode($level_url, true) : array();
            $this->assign('level_url', $level_url);
            $this->assign('objid', $objid);
            $this->display();
        }
    }

    public function relation_urlAction()
    {
        if (IS_POST) {
            $objid = I('post.objid');
            $relation_url = I('post.relation_url/a', null, 'trim');
            if (empty($relation_url['name'])) {
                $this->error('请输入名称');
            }
            $this->_check_name($relation_url['name'], '关联页名称');
            if (empty($relation_url['url_rule'])) {
                $this->error('请输入提取网址规则');
            }
            $this->success(array('relation_url' => $relation_url, 'objid' => $objid));
        } else {
            $relation_url = I('relation_url', '', 'url_b64decode');
            $objid = I('objid');
            $relation_url = $relation_url ? json_decode($relation_url, true) : array();
            $this->assign('relation_url', $relation_url);
            $this->assign('objid', $objid);
            $this->display();
        }
    }

    public function _check_name($name, $nameStr = '')
    {
        if (!preg_match('/^[\x{4e00}-\x{9fa5}\w\-]+$/u', $name)) {
            $this->error(($nameStr ? $nameStr : '名称') . '只能由汉字、字母、数字和下划线组成');
            return false;
        } else {
            return true;
        }
    }
}