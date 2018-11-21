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

use Admin\Model\CacheModel;

if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

class CpatternEvent extends CollectorEvent
{
    public $collector;
    public $config;
    public $first_loop_field = null;
    public $field_val_list = array();
    public $collect_num = 0;
    public $collected_field_list = array();
    public $used_source_urls = array();
    public $used_level_urls = array();
    public $used_cont_urls = array();
    public $original_source_urls = null;
    public $level_urls_list = array();
    public $cont_urls_list = array();
    public $relation_url_list = array();
    public $used_paging_urls = array();
    public $cur_level_urls = array();
    public $cur_source_url = '';
    public $html_cache_list = array();
    public $show_opened_tools = false;

    public function setConfig($config)
    {
        $config['url_complete'] = intval($config['url_complete']);
        $config['url_reverse'] = intval($config['url_reverse']);
        if (!empty($config['request_headers'])) {
            if (!is_array($config['request_headers']['custom_names'])) {
                $config['request_headers']['custom_names'] = array();
            }
            if (!is_array($config['request_headers']['custom_vals'])) {
                $config['request_headers']['custom_vals'] = array();
            }
            foreach ($config['request_headers']['custom_names'] as $k => $v) {
                if (empty($v)) {
                    unset($config['request_headers']['custom_names'][$k]);
                    unset($config['request_headers']['custom_vals'][$k]);
                }
            }
            $config['request_headers']['custom_names'] = array_values($config['request_headers']['custom_names']);
            $config['request_headers']['custom_vals'] = array_values($config['request_headers']['custom_vals']);
        }
        foreach ($config['source_url'] as $k => $v) {
            if (preg_match('/[\r\n]/', $v)) {
                if (preg_match_all('/^\w+\:\/\/[^\r\n]+/im', $v, $v_urls)) {
                    $v_urls = array_unique($v_urls[0]);
                    $v_urls = array_values($v_urls);
                    $config['source_url'][$k] = implode("\r\n", $v_urls);
                } else {
                    unset($config['source_url'][$k]);
                }
            } else {
                if (!preg_match('/^\w+\:\/\/.+/i', $v)) {
                    unset($config['source_url'][$k]);
                }
            }
        }
        $config['source_url'] = array_unique($config['source_url']);
        $config['source_url'] = array_filter($config['source_url']);
        $config['source_url'] = array_values($config['source_url']);
        if (!empty($config['field_list'])) {
            foreach ($config['field_list'] as $k => $v) {
                $config['field_list'][$k] = json_decode(url_b64decode($v), true);
            }
        }
        if (!empty($config['field_process'])) {
            foreach ($config['field_process'] as $k => $v) {
                $config['field_process'][$k] = json_decode(url_b64decode($v), true);
                $config['field_process'][$k] = $this->setProcess($config['field_process'][$k]);
            }
        }
        $config['common_process'] = I('process/a', null, 'trim');
        $config['common_process'] = $this->setProcess($config['common_process']);
        foreach ($config['paging_fields'] as $k => $v) {
            $config['paging_fields'][$k] = json_decode(url_b64decode($v), true);
        }
        if (!empty($config['level_urls'])) {
            foreach ($config['level_urls'] as $k => $v) {
                $config['level_urls'][$k] = json_decode(url_b64decode($v), true);
            }
        }
        if (!empty($config['relation_urls'])) {
            foreach ($config['relation_urls'] as $k => $v) {
                $config['relation_urls'][$k] = json_decode(url_b64decode($v), true);
            }
        }
        $config['url_post'] = intval($config['url_post']);
        if (!empty($config['url_posts'])) {
            if (!is_array($config['url_posts']['names'])) {
                $config['url_posts']['names'] = array();
            }
            if (!is_array($config['url_posts']['vals'])) {
                $config['url_posts']['vals'] = array();
            }
            foreach ($config['url_posts']['names'] as $k => $v) {
                if (empty($v)) {
                    unset($config['url_posts']['names'][$k]);
                    unset($config['url_posts']['vals'][$k]);
                }
            }
            $config['url_posts']['names'] = array_values($config['url_posts']['names']);
            $config['url_posts']['vals'] = array_values($config['url_posts']['vals']);
        }
        return $config;
    }

    public function init($collData)
    {
        $collData['config'] = unserialize($collData['config']);
        $this->collector = $collData;
        $keyConfig = 'collector_config_' . $collData['id'];
        $cacheConfig = F($keyConfig);
        if (empty($cacheConfig) || $cacheConfig['update_time'] != $collData['uptime']) {
            $config = $this->initConfig($collData['config']);
            F($keyConfig, array('update_time' => $collData['uptime'], 'config' => $config));
        } else {
            $config = $cacheConfig['config'];
        }
        $this->config = $config;
    }

    public function initConfig($config)
    {
        $newConfig = array();
        $newConfig['charset'] = $config['charset'] == 'custom' ? $config ['charset_custom'] : $config ['charset'];
        $newConfig['charset'] = empty($newConfig['charset']) ? 'auto' : $newConfig['charset'];
        if (!empty($config['area_start']) || !empty($config['area_end'])) {
            $newConfig['reg_source_cont'] = $config['area_start'] . (!empty($config['area_end']) ? '(?P<match>[\s\S]+?)' : '(?P<match>[\s\S]+)') . $config['area_end'];
            $newConfig['reg_source_cont'] = str_replace('(*)', '[\s\S]*?', $newConfig['reg_source_cont']);
            $newConfig['reg_source_cont'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $newConfig['reg_source_cont']);
        }
        if (!empty($config['url_rule'])) {
            $newConfig['reg_source_cont_url'] = $this->convert_sign_match($config['url_rule']);
            $newConfig['reg_source_cont_url'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $newConfig['reg_source_cont_url']);
            $newConfig['reg_source_cont_url'] = str_replace('(*)', '[\s\S]*?', $newConfig['reg_source_cont_url']);
        } else {
            $newConfig['reg_source_cont_url'] = '\bhref=[\'\"](?P<match>[^\'\"\<\>]+?)[\'\"]';
        }
        $config['url_merge'] = $this->set_merge_default($newConfig['reg_source_cont_url'], $config['url_merge']);
        if (!empty($config['url_must'])) {
            $newConfig['url_must'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $config['url_must']);
            $newConfig['url_must'] = str_replace('(*)', '[\s\S]*?', $newConfig['url_must']);
        }
        if (!empty($config['url_ban'])) {
            $newConfig['url_ban'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $config['url_ban']);
            $newConfig['url_ban'] = str_replace('(*)', '[\s\S]*?', $newConfig['url_ban']);
        }
        if (!empty($config['level_urls'])) {
            $config['new_level_urls'] = array();
            foreach ($config['level_urls'] as $luk => $luv) {
                if (!empty($luv['area'])) {
                    $luv['reg_area'] = $this->convert_sign_match($luv['area']);
                    $luv['reg_area'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $luv['reg_area']);
                    $luv['reg_area'] = str_replace('(*)', '[\s\S]*?', $luv['reg_area']);
                }
                if (!empty($luv['url_rule'])) {
                    $luv['reg_url'] = $this->convert_sign_match($luv['url_rule']);
                    $luv['reg_url'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $luv['reg_url']);
                    $luv['reg_url'] = str_replace('(*)', '[\s\S]*?', $luv['reg_url']);
                } else {
                    $luv['reg_url'] = '\bhref=[\'\"](?P<match>[^\'\"\<\>]+?)[\'\"]';
                }
                $luv['url_merge'] = $this->set_merge_default($luv['reg_url'], $luv['url_merge']);
                if (!empty($luv['url_must'])) {
                    $luv['url_must'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $luv['url_must']);
                    $luv['url_must'] = str_replace('(*)', '[\s\S]*?', $luv['url_must']);
                }
                if (!empty($luv['url_ban'])) {
                    $luv['url_ban'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $luv['url_ban']);
                    $luv['url_ban'] = str_replace('(*)', '[\s\S]*?', $luv['url_ban']);
                }
                $config['level_urls'][$luk] = $luv;
                $config['new_level_urls'][$luv['name']] = $luv;
            }
        }
        $relation_urls = array();
        if (!empty($config['relation_urls'])) {
            foreach ($config['relation_urls'] as $ruv) {
                $ruv['reg_url'] = $this->convert_sign_match($ruv['url_rule']);
                $ruv['reg_url'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $ruv['reg_url']);
                $ruv['reg_url'] = str_replace('(*)', '[\s\S]*?', $ruv['reg_url']);
                $ruv['url_merge'] = $this->set_merge_default($ruv['reg_url'], $ruv['url_merge']);
                $relation_urls[$ruv['name']] = $ruv;
            }
        }
        $relation_depth_urls = array();
        foreach ($relation_urls as $ruv) {
            $rDepth = 0;
            $rFuName = $ruv['page'];
            if (empty($rFuName)) {
                $rDepth = 0;
            } else {
                $passRelation = false;
                $rFuPage = $rFuName;
                do {
                    if (empty($relation_urls[$rFuPage])) {
                        $passRelation = true;
                        break;
                    }
                    $rFuPage = $relation_urls[$rFuPage]['page'];
                    if ($rFuPage == $rFuName) {
                        $passRelation = true;
                        break;
                    }
                    $rDepth++;
                } while (!empty($rFuPage));
                if ($passRelation) {
                    continue;
                }
            }
            $relation_depth_urls[$rDepth][$ruv['name']] = $ruv;
        }
        ksort($relation_depth_urls);
        $config['new_relation_urls'] = array();
        foreach ($relation_depth_urls as $rurls) {
            if (is_array($rurls)) {
                $config['new_relation_urls'] = array_merge($config['new_relation_urls'], $rurls);
            }
        }
        if (!empty($config['field_list'])) {
            foreach ($config['field_list'] as $fk => $fv) {
                if ('rule' == $fv['module']) {
                    $fv['reg_rule'] = $this->convert_sign_match($fv['rule']);
                    $fv['reg_rule'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $fv['reg_rule']);
                    $fv['reg_rule'] = str_replace('(*)', '[\s\S]*?', $fv['reg_rule']);
                    $fv['rule_merge'] = $this->set_merge_default($fv['reg_rule'], $fv['rule_merge']);
                } elseif ('extract' == $fv['module']) {
                    if (!empty($fv['extract_rule'])) {
                        $fv['reg_extract_rule'] = $this->convert_sign_match($fv['extract_rule']);
                        $fv['reg_extract_rule'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $fv['reg_extract_rule']);
                        $fv['reg_extract_rule'] = str_replace('(*)', '[\s\S]*?', $fv['reg_extract_rule']);
                        $fv['extract_rule_merge'] = $this->set_merge_default($fv['reg_extract_rule'], '');
                    }
                }
                $config['field_list'][$fk] = $fv;
            }
        }
        if (!empty($config['field_process'])) {
            foreach ($config['field_process'] as $k => $v) {
                $config['field_process'][$k] = $this->initProcess($v);
            }
        }
        if (!empty($config['common_process'])) {
            $config['common_process'] = $this->initProcess($config['common_process']);
        }
        if (!empty($config['paging']['area'])) {
            $config['paging']['reg_area'] = $this->convert_sign_match($config['paging']['area']);
            $config['paging']['reg_area'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $config['paging']['reg_area']);
            $config['paging']['reg_area'] = str_replace('(*)', '[\s\S]*?', $config['paging']['reg_area']);
            $config['paging']['reg_area_merge'] = $this->set_merge_default($config['paging']['reg_area'], '');
        }
        if (!empty($config['paging']['url_rule'])) {
            $config['paging']['reg_url'] = $this->convert_sign_match($config['paging']['url_rule']);
            $config['paging']['reg_url'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $config['paging']['reg_url']);
            $config['paging']['reg_url'] = str_replace('(*)', '[\s\S]*?', $config['paging']['reg_url']);
        }
        $config['paging']['url_merge'] = $this->set_merge_default($config['paging']['reg_url'], $config['paging']['url_merge']);
        if (!empty($config['paging']['url_must'])) {
            $config['paging']['url_must'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $config['paging']['url_must']);
            $config['paging']['url_must'] = str_replace('(*)', '[\s\S]*?', $config['paging']['url_must']);
        }
        if (!empty($config['paging']['url_ban'])) {
            $config['paging']['url_ban'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $config['paging']['url_ban']);
            $config['paging']['url_ban'] = str_replace('(*)', '[\s\S]*?', $config['paging']['url_ban']);
        }
        $module_normal_fields = array();
        $module_extract_fields = array();
        $module_merge_fields = array();
        foreach ($config['field_list'] as $fk => $fv) {
            $fieldModule = strtolower($fv['module']);
            $fieldConfig = array('field' => $fv, 'process' => $config['field_process'][$fk]);
            if ('extract' == $fieldModule) {
                $module_extract_fields[$fv['name']] = $fieldConfig;
            } elseif ('merge' == $fieldModule) {
                $module_merge_fields[$fv['name']] = $fieldConfig;
            } else {
                $module_normal_fields[$fv['name']] = $fieldConfig;
            }
        }
        $config['new_field_list'] = array_merge($module_normal_fields, $module_extract_fields, $module_merge_fields);
        $new_paging_fields = array('normal' => array(), 'extract' => array(), 'merge' => array(),);
        foreach ($config['paging_fields'] as $pfk => $pfield) {
            $pfield['delimiter'] = str_replace(array('\r', '\n'), array("\r", "\n"), $pfield['delimiter']);
            $config['paging_fields'][$pfk] = $pfield;
            if (!empty($module_normal_fields[$pfield['field']])) {
                $new_paging_fields['normal'][$pfield['field']] = $pfield;
            } elseif (!empty($module_extract_fields[$pfield['field']])) {
                $new_paging_fields['extract'][$pfield['field']] = $pfield;
            } elseif (!empty($module_merge_fields[$pfield['field']])) {
                $new_paging_fields['merge'][$pfield['field']] = $pfield;
            }
        }
        $config['new_paging_fields'] = array_merge($new_paging_fields['normal'], $new_paging_fields['extract'], $new_paging_fields['merge']);
        $config = array_merge($config, $newConfig);
        return $config;
    }

    public function _get_urls($source_url, $config, $is_level = false)
    {
        $is_level = $is_level ? '多级' : '';
        $html = $this->get_html($source_url);
        if (empty($html)) {
            return $this->error($is_level . '页面为空');
        }
        $base_url = $this->match_base_url($source_url, $html);
        $domain_url = $this->match_domain_url($source_url);
        if (!empty($config['reg_area'])) {
            if (preg_match('/' . $config['reg_area'] . '/i', $html, $source_cont)) {
                $html = $source_cont['match'];
            } else {
                return $this->error("未提取到{$is_level}区域内容！");
            }
        }
        $cont_urls = array();
        if (!empty($config['reg_url'])) {
            if (!empty($config['url_merge'])) {
                $sign_match = $this->sign_addslashes(cp_sign('match', '(?P<num>\d*)'));
                if (preg_match_all('/' . $sign_match . '/i', $config['url_merge'], $match_signs)) {
                    if (preg_match_all('/' . $config['reg_url'] . '/i', $html, $match_urls, PREG_SET_ORDER)) {
                        if (isset($this->config['url_op'])) {
                            $op_not_complete = in_array('not_complete', $this->config['url_op']) ? true : false;
                        } else {
                            if (isset($this->config['url_complete'])) {
                                $op_not_complete = $this->config['url_complete'] ? false : true;
                            } else {
                                $op_not_complete = false;
                            }
                        }
                        foreach ($match_urls as $match_url) {
                            $re_match = array();
                            foreach ($match_signs['num'] as $ms_k => $ms_v) {
                                $re_match[$ms_k] = $match_url['match' . $ms_v];
                            }
                            $cont_url = str_replace($match_signs[0], $re_match, $config['url_merge']);
                            if (!$op_not_complete) {
                                $cont_url = $this->create_complete_url($cont_url, $base_url, $domain_url);
                            }
                            if (!empty($config['url_must'])) {
                                if (!preg_match('/' . $config['url_must'] . '/i', $cont_url)) {
                                    continue;
                                }
                            }
                            if (!empty($config['url_ban'])) {
                                if (preg_match('/' . $config['url_ban'] . '/i', $cont_url)) {
                                    continue;
                                }
                            }
                            if (!empty($cont_url)) {
                                if (strpos($cont_url, ' ') == false) {
                                    $cont_urls[] = $cont_url;
                                }
                            }
                        }
                    }
                }
            }
        }
        if (empty($cont_urls)) {
            return $this->error("未获取到" . ($is_level ? $is_level : '内容') . "网址！");
        } else {
            $cont_urls = array_unique($cont_urls);
            if (!empty($this->config['url_reverse'])) {
                $cont_urls = array_reverse($cont_urls);
            }
            if (!empty($this->config['url_post'])) {
                $postParams = array();
                if (!empty($this->config['url_posts']['names'])) {
                    foreach ($this->config['url_posts']['names'] as $k => $v) {
                        if (!empty($v)) {
                            $postParams[] = $v . '=' . rawurlencode($this->config['url_posts']['vals'][$k]);
                        }
                    }
                }
                if (!empty($postParams)) {
                    $postParams = implode('&', $postParams);
                    foreach ($cont_urls as $k => $v) {
                        $v .= strpos($v, '?') === false ? '?' : '&';
                        $v .= $postParams;
                        $cont_urls[$k] = $v;
                    }
                }
            }
            return array_values($cont_urls);
        }
    }

    public function getContUrls($source_url)
    {
        if (empty($source_url)) {
            return $this->error('请输入起始网址');
        }
        $config = array('reg_area' => $this->config['reg_source_cont'], 'reg_url' => $this->config['reg_source_cont_url'], 'url_merge' => $this->config['url_merge'], 'url_must' => $this->config['url_must'], 'url_ban' => $this->config['url_ban'],);
        return $this->_get_urls($source_url, $config);
    }

    public function getLevelUrls($parent_url, $level = 1)
    {
        $level = $level > 1 ? $level : 1;
        $config = $this->config['level_urls'][$level - 1];
        if (empty($config)) {
            return $this->error('没有' . ($level) . '级网址规则');
        }
        if (empty($config['reg_url'])) {
            return $this->error('必须填写多级“提取网址规则”');
        }
        if (empty($parent_url)) {
            return $this->error('请输入父级网址');
        }
        return $this->_get_urls($parent_url, $config, true);
    }

    public function getPagingUrls($from_url, &$html, $is_test = false)
    {
        $paging_urls = array();
        if ($this->config['paging']['open']) {
            if (empty($html)) {
                $html = $this->get_html($from_url);
            }
            if (!empty($this->config['paging']['reg_url'])) {
                if (!empty($this->config['new_paging_fields'])) {
                    $base_url = $this->match_base_url($from_url, $html);
                    $domain_url = $this->match_domain_url($from_url);
                    $paging_area = '';
                    if (!empty($this->config['paging']['reg_area'])) {
                        $sign_match = $this->sign_addslashes(cp_sign('match', '(?P<num>\d*)'));
                        if (preg_match_all('/' . $sign_match . '/i', $this->config['paging']['reg_area_merge'], $match_signs)) {
                            if (preg_match('/' . $this->config['paging']['reg_area'] . '/i', $html, $m_paging_area)) {
                                $re_match = array();
                                foreach ($match_signs['num'] as $ms_k => $ms_v) {
                                    $re_match[$ms_k] = $m_paging_area['match' . $ms_v];
                                }
                                $paging_area = str_replace($match_signs[0], $re_match, $this->config['paging']['reg_area_merge']);
                            }
                        } else {
                            if (preg_match('/' . $this->config['paging']['reg_area'] . '/i', $html, $m_paging_area)) {
                                $paging_area = $m_paging_area[0];
                            }
                        }
                    } else {
                        $paging_area = $html;
                    }
                    if (!empty($paging_area)) {
                        if (!empty($this->config['paging']['url_complete'])) {
                            $paging_area = preg_replace_callback('/(?<=\bhref\=[\'\"])([^\'\"]*)(?=[\'\"])/i', function ($matche_p_a) use ($base_url, $domain_url) {
                                return CpatternEvent::create_complete_url($matche_p_a[1], $base_url, $domain_url);
                            }, $paging_area);
                        }
                        $sign_match = $this->sign_addslashes(cp_sign('match', '(?P<num>\d*)'));
                        $m_paging_urls = array();
                        if (preg_match_all('/' . $sign_match . '/i', $this->config['paging']['url_merge'], $match_signs)) {
                            if (preg_match_all('/' . $this->config['paging']['reg_url'] . '/i', $paging_area, $match_page_urls, PREG_SET_ORDER)) {
                                foreach ($match_page_urls as $match_page_url) {
                                    $re_match = array();
                                    foreach ($match_signs['num'] as $ms_k => $ms_v) {
                                        $re_match[$ms_k] = $match_page_url['match' . $ms_v];
                                    }
                                    $m_paging_urls[] = str_replace($match_signs[0], $re_match, $this->config['paging']['url_merge']);
                                }
                            }
                        } else {
                            if (preg_match_all('/' . $this->config['paging']['reg_url'] . '/i', $paging_area, $match_page_urls)) {
                                $m_paging_urls = $match_page_urls[0];
                            }
                        }
                        foreach ($m_paging_urls as $purl) {
                            if (!empty($this->config['paging']['url_must'])) {
                                if (!preg_match('/' . $this->config['paging']['url_must'] . '/i', $purl)) {
                                    continue;
                                }
                            }
                            if (!empty($this->config['paging']['url_ban'])) {
                                if (preg_match('/' . $this->config['paging']['url_ban'] . '/i', $purl)) {
                                    continue;
                                }
                            }
                            if ($from_url == $purl) {
                                continue;
                            }
                            if (strpos($purl, ' ') == false) {
                                $paging_urls[] = $purl;
                            }
                        }
                        if (!empty($paging_urls)) {
                            $paging_urls = array_filter($paging_urls);
                            $paging_urls = array_unique($paging_urls);
                            $paging_urls = array_values($paging_urls);
                        } else {
                            if ($is_test) {
                                return $this->error('未获取到分页链接，请检查分页链接规则');
                            }
                        }
                    } else {
                        if ($is_test) {
                            return $this->error('未获取到分页区域，请检查分页区域规则');
                        }
                    }
                } else {
                    if ($is_test) {
                        return $this->error('请添加分页内容字段');
                    }
                }
            } else {
                if ($is_test) {
                    return $this->error('必须填写分页链接规则');
                }
            }
        } else {
            if ($is_test) {
                return $this->error('未开启分页');
            }
        }
        return $paging_urls;
    }

    public function setField($field_config, $cont_url, $html)
    {
        $cont_url_md5 = md5($cont_url);
        $field_process = $field_config['process'];
        $field_params = $field_config['field'];
        $module = strtolower($field_params['module']);
        if (!empty($field_params['source']) && in_array($module, array('rule', 'xpath', 'json', 'auto'))) {
            $field_source_url = '';
            $source_echo_msg = '——采集';
            if ('source_url' == $field_params['source']) {
                $field_source_url = $this->cur_source_url;
                $source_echo_msg .= '起始页';
            } elseif (preg_match('/^relation_url:(.+)$/i', $field_params['source'], $relationName)) {
                $relationName = $relationName[1];
                $field_source_url = $this->getRelationUrl($relationName, $cont_url, $html);
                $source_echo_msg .= "关联页“{$relationName}”";
            } elseif (preg_match('/^level_url:(.+)$/i', $field_params['source'], $levelName)) {
                $levelName = $levelName[1];
                if (empty($this->config['new_level_urls'][$levelName])) {
                    return;
                }
                if (empty($this->cur_level_urls[$levelName])) {
                    return;
                }
                $field_source_url = $this->cur_level_urls[$levelName];
                $source_echo_msg .= "多级页“{$levelName}”";
            }
            if (empty($field_source_url)) {
                return;
            }
            if ($field_source_url != $cont_url) {
                $cont_url = $field_source_url;
                $this->echo_msg($source_echo_msg . "：<a href='{$field_source_url}' target='_blank'>{$field_source_url}</a>", 'black');
                $html = $this->get_html($field_source_url, true);
            }
        }
        static $fieldArr1 = array('words', 'num', 'time', 'list');
        static $fieldArr2 = array('auto', 'json');
        static $baseUrls = array();
        static $domainUrls = array();
        $urlMd5 = md5($cont_url);
        if (empty($baseUrls[$urlMd5])) {
            $baseUrls[$urlMd5] = $this->match_base_url($cont_url, $html);
        }
        if (empty($domainUrls[$urlMd5])) {
            $domainUrls[$urlMd5] = $this->match_domain_url($cont_url);
        }
        $base_url = $baseUrls[$urlMd5];
        $domain_url = $domainUrls[$urlMd5];
        $val = '';
        $field_func = 'field_module_' . $module;
        if (method_exists($this, $field_func)) {
            if ('extract' == $module) {
                if (is_array($this->field_val_list[$field_params['extract']]['values'][$cont_url_md5])) {
                    $val = array();
                    foreach ($this->field_val_list[$field_params['extract']]['values'][$cont_url_md5] as $k => $v) {
                        $extract_field_val = array('value' => $v, 'img' => $this->field_val_list[$field_params['extract']]['imgs'][$cont_url_md5][$k],);
                        $val[$k] = $this->field_module_extract($field_params, $extract_field_val, $base_url, $domain_url);
                    }
                } else {
                    $extract_field_val = array('value' => $this->field_val_list[$field_params['extract']]['values'][$cont_url_md5], 'img' => $this->field_val_list[$field_params['extract']]['imgs'][$cont_url_md5],);
                    $val = $this->field_module_extract($field_params, $extract_field_val, $base_url, $domain_url);
                }
            } elseif ('merge' == $module) {
                if (empty($this->first_loop_field)) {
                    $cur_field_val_list = array();
                    foreach ($this->field_val_list as $k => $v) {
                        $cur_field_val_list[$k] = array('value' => $v['values'][$cont_url_md5], 'img' => $v['imgs'][$cont_url_md5]);
                    }
                    $val = $this->field_module_merge($field_params, $cur_field_val_list);
                } else {
                    $val = array();
                    foreach ($this->field_val_list[$this->first_loop_field]['values'][$cont_url_md5] as $v_k => $v_v) {
                        $cur_field_val_list = array();
                        foreach ($this->field_val_list as $k => $v) {
                            $cur_field_val_list[$k] = array('value' => (is_array($v['values'][$cont_url_md5]) ? $v['values'][$cont_url_md5][$v_k] : $v['values'][$cont_url_md5]), 'img' => (is_array($v['imgs'][$cont_url_md5][$v_k]) ? $v['imgs'][$cont_url_md5][$v_k] : $v['imgs'][$cont_url_md5]));
                        }
                        $val[$v_k] = $this->field_module_merge($field_params, $cur_field_val_list);
                    }
                }
            } elseif (in_array($module, $fieldArr1)) {
                $val = $this->$field_func($field_params);
            } elseif (in_array($module, $fieldArr2)) {
                $val = $this->$field_func($field_params, $html, $cont_url);
            } else {
                $val = $this->$field_func($field_params, $html);
            }
        }
        $vals = null;
        if (is_array($val)) {
            $is_loop = true;
            $vals = array_values($val);
        } else {
            $is_loop = false;
            $vals = array($val);
        }
        $field_name = $field_params['name'];
        if (!isset($this->field_val_list[$field_name])) {
            $this->field_val_list[$field_name] = array('values' => array(), 'imgs' => array());
        }
        foreach ($vals as $v_k => $val) {
            if (!empty($field_process)) {
                $val = $this->processField($val, $field_process);
            }
            if (!empty($this->config['common_process'])) {
                $val = $this->processField($val, $this->config['common_process']);
            }
            $val = preg_replace_callback('/(?<=\bhref\=[\'\"])([^\'\"]*)(?=[\'\"])/i', function ($matche) use ($base_url, $domain_url) {
                return CpatternEvent::create_complete_url($matche[1], $base_url, $domain_url);
            }, $val);
            $val = preg_replace_callback('/(?<=\bsrc\=[\'\"])([^\'\"]*)(?=[\'\"])/i', function ($matche) use ($base_url, $domain_url) {
                return CpatternEvent::create_complete_url($matche[1], $base_url, $domain_url);
            }, $val);
            if ($is_loop) {
                if (!isset($this->field_val_list[$field_name]['values'][$cont_url_md5])) {
                    $this->field_val_list[$field_name]['values'][$cont_url_md5] = array();
                    $this->field_val_list[$field_name]['imgs'][$cont_url_md5] = array();
                }
                $this->field_val_list[$field_name]['values'][$cont_url_md5][$v_k] = $val;
            } else {
                $this->field_val_list[$field_name]['values'][$cont_url_md5] = $val;
            }
            if (!empty($GLOBALS['config']['caiji']['download_img']) && !empty($val)) {
                $valImgs = array();
                if (preg_match_all('/<img[^<>]*\bsrc=[\'\"]*(\w+\:\/\/[^\'\"\s]+)[\'\"]*/i', $val, $imgUrls)) {
                    $valImgs = is_array($imgUrls[1]) ? $imgUrls[1] : array();
                }
                if ('extract' == $module && 'cover' == $field_params['extract_module']) {
                    $valImgs = array_merge($valImgs, array($val));
                }
                $noImgVal = preg_replace_callback('/\{\[img\]\}(http[s]{0,1}\:\/\/[^\s]+?)\{\[\/img\]\}/i', function ($matche) use (&$valImgs) {
                    $valImgs[] = $matche[1];
                    return $matche[1];
                }, $val);
                if ($noImgVal != $val) {
                    if ($is_loop) {
                        $this->field_val_list[$field_name]['values'][$cont_url_md5][$v_k] = $noImgVal;
                    } else {
                        $this->field_val_list[$field_name]['values'][$cont_url_md5] = $noImgVal;
                    }
                }
                if (!empty($valImgs)) {
                    $valImgs = array_unique($valImgs);
                    $valImgs = array_values($valImgs);
                    if ($is_loop) {
                        $this->field_val_list[$field_name]['imgs'][$cont_url_md5][$v_k] = $valImgs;
                    } else {
                        $this->field_val_list[$field_name]['imgs'][$cont_url_md5] = $valImgs;
                    }
                }
            }
        }
    }

    public function setPagingFields($cont_url, $page_url)
    {
        $contMd5 = md5($cont_url);
        $pageMd5 = md5($page_url);
        if (empty($page_url)) {
            return $this->error('请输入分页网址');
        }
        if (!preg_match('/^\w+\:\/\//', $page_url)) {
            return $this->error($page_url . '网址不完整');
        }
        if (empty($this->config['paging']['max']) || (count($this->used_paging_urls[$contMd5]) < $this->config['paging']['max'])) {
            $this->set_html_interval();
            $this->echo_msg("——采集分页：<a href='{$page_url}' target='_blank'>{$page_url}</a>", 'black');
            $html = $this->get_html($page_url);
            if (empty($html)) {
                return $this->error('分页获取失败：' . $page_url);
            }
            if (!isset($this->used_paging_urls[$contMd5][$pageMd5])) {
                $this->used_paging_urls[$contMd5][$pageMd5] = $page_url;
                foreach ($this->config['new_paging_fields'] as $v) {
                    $this->setField($this->config['new_field_list'][$v['field']], $page_url, $html);
                }
            }
            $paging_urls = $this->getPagingUrls($page_url, $html);
            if (!empty($paging_urls)) {
                $nextUrl = '';
                foreach ($paging_urls as $purl) {
                    if (!isset($this->used_paging_urls[$contMd5][md5($purl)]) && $cont_url != $purl) {
                        $nextUrl = $purl;
                        break;
                    }
                }
                if (!empty($nextUrl)) {
                    $this->setPagingFields($cont_url, $nextUrl);
                }
            }
        }
    }

    public function match_rule($html, $rule, $merge, $multi = false, $multi_str = '')
    {
        $val = '';
        $sign_match = $this->sign_addslashes(cp_sign('match', '(?P<num>\d*)'));
        if (!empty($rule) && preg_match_all('/' . $sign_match . '/i', $merge, $match_signs)) {
            $multiStr = '';
            if (!empty($multi)) {
                preg_match_all('/' . $rule . '/i', $html, $match_conts, PREG_SET_ORDER);
                $multiStr = str_replace(array('\r', '\n'), array("\r", "\n"), $multi_str);
            } else {
                if (preg_match('/' . $rule . '/i', $html, $match_cont)) {
                    $match_conts = array($match_cont);
                }
            }
            $curI = 0;
            foreach ($match_conts as $match_cont) {
                $curI++;
                $re_match = array();
                foreach ($match_signs['num'] as $ms_k => $ms_v) {
                    $re_match[$ms_k] = $match_cont['match' . $ms_v];
                }
                $val .= ($curI <= 1 ? '' : $multiStr) . str_replace($match_signs[0], $re_match, $merge);
            }
        }
        return $val;
    }

    public function getRelationUrl($name, $cont_url, $html)
    {
        if (empty($html)) {
            $html = $this->get_html($cont_url, true);
        }
        if (empty($html)) {
            return '';
        }
        $relation_url = $this->config['new_relation_urls'][$name];
        if (empty($relation_url)) {
            return '';
        }
        $page = $relation_url['page'];
        $pass = false;
        $depth_pages = array();
        $depth = 0;
        while (!empty($page)) {
            if ($page == $name) {
                $pass = true;
                break;
            }
            if (empty($this->config['new_relation_urls'][$page])) {
                $pass = true;
                break;
            }
            $depth++;
            $depth_pages[$depth] = $page;
            $page = $this->config['new_relation_urls'][$page]['page'];
        }
        if ($pass) {
            return '';
        }
        $relationUrl = $this->match_rule($html, $relation_url['reg_url'], $relation_url['url_merge']);
        $this->relation_url_list[$cont_url][$relation_url['page']] = $relationUrl;
        if (!empty($depth_pages)) {
            krsort($depth_pages);
            foreach ($depth_pages as $page) {
                if (empty($relationUrl)) {
                    return '';
                }
                if (!isset($this->relation_url_list[$cont_url][$page])) {
                    $relationHtml = $this->get_html($relationUrl, true);
                    if (empty($relationHtml)) {
                        return '';
                    }
                    $relationUrl = $this->match_rule($relationHtml, $this->config['new_relation_urls'][$page]['reg_url'], $this->config['new_relation_urls'][$page]['url_merge']);
                    $this->relation_url_list[$cont_url][$page] = $relationUrl;
                } else {
                    $relationUrl = $this->relation_url_list[$cont_url][$page];
                }
            }
        }
        return $relationUrl;
    }

    public function getFields($cont_url)
    {
        $this->field_val_list = array();
        $this->first_loop_field = null;
        if (empty($cont_url)) {
            return $this->error('请输入内容页网址');
        }
        if (!preg_match('/^\w+\:\/\//', $cont_url)) {
            return $this->error($cont_url . '网址不完整');
        }
        $html = $this->get_html($cont_url, false, $this->config['url_post']);
        if (empty($html)) {
            return $this->error('抓取页面失败');
        }
        foreach ($this->config['new_field_list'] as $field_config) {
            $this->setField($field_config, $cont_url, $html);
        }
        $paging_urls = $this->getPagingUrls($cont_url, $html);
        if (!empty($paging_urls)) {
            $this->setPagingFields($cont_url, reset($paging_urls));
        }
        $val_list = array();
        if (!empty($this->field_val_list)) {
            if (empty($this->first_loop_field)) {
                foreach ($this->field_val_list as $fieldName => $fieldVal) {
                    $val_values = array_filter($fieldVal['values']);
                    $val_values = implode($this->config['new_paging_fields'][$fieldName]['delimiter'], $val_values);
                    $val_imgs = array();
                    if (!empty($fieldVal['imgs'])) {
                        foreach ($fieldVal['imgs'] as $v) {
                            if (!empty($v)) {
                                if (is_array($v)) {
                                    $val_imgs = array_merge($val_imgs, $v);
                                } else {
                                    $val_imgs[] = $v;
                                }
                            }
                        }
                        if (!empty($val_imgs)) {
                            $val_imgs = array_unique($val_imgs);
                            $val_imgs = array_filter($val_imgs);
                            $val_imgs = array_values($val_imgs);
                        }
                    }
                    $val_list[$fieldName] = array('value' => $val_values, 'img' => $val_imgs);
                }
            } else {
                foreach ($this->field_val_list[$this->first_loop_field]['values'] as $page_key => $page_vals) {
                    if (empty($page_vals)) {
                        continue;
                    }
                    foreach ($page_vals as $loop_index => $loop_val) {
                        $vals = array();
                        foreach ($this->field_val_list as $fieldName => $fieldVals) {
                            if (is_array($fieldVals['values'][$page_key])) {
                                $val_values = $fieldVals['values'][$page_key][$loop_index];
                                $val_imgs = $fieldVals['imgs'][$page_key][$loop_index];
                            } else {
                                $val_values = $fieldVals['values'][$page_key];
                                $val_imgs = $fieldVals['imgs'][$page_key];
                            }
                            if (!empty($val_imgs)) {
                                $val_imgs = array_unique($val_imgs);
                                $val_imgs = array_filter($val_imgs);
                                $val_imgs = array_values($val_imgs);
                            }
                            $vals[$fieldName] = array('value' => $val_values, 'img' => $val_imgs);
                        }
                        $val_list[] = $vals;
                    }
                }
            }
        }
        return $val_list ? $val_list : array();
    }

    public function field_module_rule($field_params, &$html)
    {
        $val = '';
        $sign_match = $this->sign_addslashes(cp_sign('match', '(?P<num>\d*)'));
        if (!empty($field_params['reg_rule']) && preg_match_all('/' . $sign_match . '/i', $field_params['rule_merge'], $match_signs)) {
            $multiStr = '';
            $is_loop = false;
            if (!empty($field_params['rule_multi'])) {
                preg_match_all('/' . $field_params['reg_rule'] . '/i', $html, $match_conts, PREG_SET_ORDER);
                $is_loop = 'loop' == $field_params['rule_multi_type'] ? true : false;
                if ($is_loop) {
                    if (empty($this->first_loop_field)) {
                        $this->first_loop_field = $field_params['name'];
                    }
                    $val = array();
                } else {
                    $multiStr = str_replace(array('\r', '\n'), array("\r", "\n"), $field_params['rule_multi_str']);
                }
            } else {
                if (preg_match('/' . $field_params['reg_rule'] . '/i', $html, $match_cont)) {
                    $match_conts = array($match_cont);
                }
            }
            $curI = 0;
            foreach ($match_conts as $match_cont) {
                $curI++;
                $re_match = array();
                foreach ($match_signs['num'] as $ms_k => $ms_v) {
                    $re_match[$ms_k] = $match_cont['match' . $ms_v];
                }
                $contVal = str_replace($match_signs[0], $re_match, $field_params['rule_merge']);
                if ($is_loop) {
                    $val[] = $contVal;
                } else {
                    $val .= ($curI <= 1 ? '' : $multiStr) . $contVal;
                }
            }
        }
        return $val;
    }

    public function field_module_xpath($field_params, &$html)
    {
        $vals = '';
        if (!empty($field_params['xpath'])) {
            $dom = new \DOMDocument;
            @$dom->loadHTML('<meta http-equiv="Content-Type" content="text/html;charset=utf-8">' . $html);
            $dom->normalize();
            $xPath = new \DOMXPath($dom);
            $xpath_attr = strtolower($field_params['xpath_attr']);
            $xpath_attr = 'custom' == $xpath_attr ? strtolower($field_params['xpath_attr_custom']) : $xpath_attr;
            $normal_attr = true;
            if (in_array($xpath_attr, array('innerhtml', 'outerhtml', 'text'))) {
                $normal_attr = false;
            }
            $xpath_q = trim($field_params['xpath']);
            if (!empty($xpath_attr)) {
                if (preg_match('/\/\@[\w\-]+$/', $xpath_q)) {
                    $xpath_q = preg_replace('/\@[\w\-]+$/', '', $xpath_q);
                }
                if ($normal_attr) {
                    $xpath_q = $xpath_q . (preg_match('/\/$/', $xpath_q) ? '' : '/') . '@' . $xpath_attr;
                }
            } else {
                if (!preg_match('/\/\@[\w\-]+$/', $xpath_q)) {
                    $xpath_attr = 'innerhtml';
                    $normal_attr = false;
                }
            }
            $nodes = $xPath->query($xpath_q);
            $multiStr = '';
            $is_loop = false;
            if (!empty($field_params['xpath_multi'])) {
                $is_loop = 'loop' == $field_params['xpath_multi_type'] ? true : false;
                if ($is_loop) {
                    if (empty($this->first_loop_field)) {
                        $this->first_loop_field = $field_params['name'];
                    }
                    $vals = array();
                } else {
                    $multiStr = str_replace(array('\r', '\n'), array("\r", "\n"), $field_params['xpath_multi_str']);
                }
            }
            $curI = 0;
            foreach ($nodes as $node) {
                $curI++;
                $val = ($curI <= 1 ? '' : $multiStr);
                if ($normal_attr) {
                    $val .= $node->nodeValue;
                } else {
                    switch ($xpath_attr) {
                        case 'innerhtml':
                            $nchilds = $node->childNodes;
                            foreach ($nchilds as $nchild) {
                                $val .= $nchild->ownerDocument->saveHTML($nchild);
                            }
                            break;
                        case 'outerhtml':
                            $val .= $node->ownerDocument->saveHTML($node);
                            break;
                        case 'text':
                            $nchilds = $node->childNodes;
                            foreach ($nchilds as $nchild) {
                                $val .= $nchild->ownerDocument->saveHTML($nchild);
                            }
                            $val = $this->filter_html_tags($val, array('style', 'script', 'object'));
                            $val = strip_tags($val);
                            break;
                    }
                }
                if ($is_loop) {
                    $vals[] = $val;
                } else {
                    $vals .= $val;
                }
                if (empty($field_params['xpath_multi'])) {
                    break;
                }
            }
        }
        return $vals;
    }

    public function field_module_auto($field_params, &$html, $cur_url)
    {
        switch (strtolower($field_params['auto'])) {
            case 'title':
                $val = $this->get_title($html);
                break;
            case 'content':
                $val = $this->get_content($html);
                break;
            case 'keywords':
                $val = $this->get_keywords($html);
                break;
            case 'description':
                $val = $this->get_description($html);
                break;
            case 'url':
                $val = $cur_url;
                break;
        }
        return $val;
    }

    public function field_module_words($field_params)
    {
        return $field_params['words'];
    }

    public function field_module_num($field_params)
    {
        $start = intval($field_params['num_start']);
        $end = intval($field_params['num_end']);
        return rand($start, $end);
    }

    public function field_module_time($field_params)
    {
        $val = '';
        $start = empty($field_params['time_start']) ? NOW_TIME : strtotime($field_params['time_start']);
        $end = empty($field_params['time_end']) ? NOW_TIME : strtotime($field_params['time_end']);
        $time = rand($start, $end);
        if (empty($field_params['time_stamp'])) {
            $fmt = empty($field_params['time_format']) ? 'Y-m-d H:i' : str_replace(array('[年]', '[月]', '[日]', '[时]', '[分]', '[秒]'), array('Y', 'm', 'd', 'H', 'i', 's'), $field_params['time_format']);
            $val = date($fmt, $time);
        } else {
            $val = $time;
        }
        return $val;
    }

    public function field_module_list($field_params)
    {
        $val = '';
        if (preg_match_all('/[^\r\n]+/', $field_params['list'], $str_list)) {
            $str_list = $str_list[0];
            $randi = array_rand($str_list, 1);
            $val = $str_list[$randi];
        }
        return $val;
    }

    public function field_module_merge($field_params, $val_list)
    {
        $val = '';
        if (preg_match_all('/\[\x{5b57}\x{6bb5}\:(.+?)\]/u', $field_params['merge'], $match_fields)) {
            $val = $field_params['merge'];
            for ($i = 0; $i < count($match_fields[0]); $i++) {
                $val = str_replace($match_fields[0][$i], $val_list[$match_fields[1][$i]]['value'], $val);
            }
        }
        return $val;
    }

    public function field_module_json($field_params, $html, $cur_url = '')
    {
        static $jsonList = array();
        $jsonKey = !empty($cur_url) ? md5($cur_url) : md5($html);
        if (!isset($jsonList[$jsonKey])) {
            $jsonList[$jsonKey] = json_decode($html, true);
        }
        $val = $this->field_module_json_data($field_params, $jsonList[$jsonKey]);
        return $val;
    }

    public function field_module_json_data($field_params, $json)
    {
        $val = '';
        if (!empty($json)) {
            if (!empty($field_params['json'])) {
                $jsonFmt = str_replace(array('"', "'", '[', ' '), '', $field_params['json']);
                $jsonFmt = str_replace(']', '.', $jsonFmt);
                $jsonFmt = trim($jsonFmt, '.');
                $jsonFmt = explode('.', $jsonFmt);
                $jsonFmt = array_values($jsonFmt);
                if (!empty($jsonFmt)) {
                    $val = $json;
                    $prevKey = '';
                    foreach ($jsonFmt as $i => $key) {
                        if ($prevKey == '*') {
                            $new_field_params = $field_params;
                            $new_field_params['json'] = array_slice($jsonFmt, $i);
                            $new_field_params['json'] = implode('.', $new_field_params['json']);
                            foreach ($val as $vk => $vv) {
                                $val[$vk] = $this->field_module_json_data($new_field_params, $vv);
                            }
                            break;
                        } else {
                            if ($key != '*') {
                                $val = $val[$key];
                            }
                        }
                        $prevKey = $key;
                    }
                }
            }
        }
        if (is_array($val)) {
            $json_arr = strtolower($field_params['json_arr']);
            if (empty($json_arr)) {
                $json_arr = 'implode';
            }
            switch ($json_arr) {
                case 'implode':
                    $arrImplode = str_replace(array('\r', '\n'), array("\r", "\n"), $field_params['json_arr_implode']);
                    $val = array_implode($arrImplode, $val);
                    break;
                case 'jsonencode':
                    $val = json_encode($val);
                    break;
                case 'serialize':
                    $val = serialize($val);
                    break;
            }
        }
        return $val;
    }

    public function field_module_extract($field_params, $extract_field_val, $base_url, $domain_url)
    {
        $field_html = $extract_field_val['value'];
        if (empty($field_html)) {
            return '';
        }
        $val = '';
        $extract_module = strtolower($field_params['extract_module']);
        switch ($extract_module) {
            case 'cover':
                if (!empty($extract_field_val['img'])) {
                    $val = reset($extract_field_val['img']);
                } else {
                    if (preg_match('/<img[^<>]*\bsrc=[\'\"](?P<url>[^\'\"]+?)[\'\"]/i', $field_html, $cover)) {
                        $cover = $cover['url'];
                        $cover = $this->create_complete_url($cover, $base_url, $domain_url);
                        $val = $cover;
                    }
                }
                break;
            case 'phone':
                $field_html = $this->filter_html_tags($field_html, 'style,script,object');
                $field_html = strip_tags($field_html);
                if (preg_match('/\d{11}/', $field_html, $phone)) {
                    $val = $phone[0];
                }
                break;
            case 'email':
                $field_html = $this->filter_html_tags($field_html, 'style,script,object');
                $field_html = strip_tags($field_html);
                if (preg_match('/[\w\-]+\@[\w\-\.]+/i', $field_html, $email)) {
                    $val = $email[0];
                }
                break;
            case 'rule':
                $val = $this->field_module_rule(array('reg_rule' => $field_params['reg_extract_rule']), $field_html);
                if (empty($val)) {
                    if (preg_match('/' . $field_params['reg_extract_rule'] . '/i', $field_html, $val)) {
                        $val = $val[0];
                    }
                }
                break;
            case 'xpath':
                $val = $this->field_module_xpath(array('xpath' => $field_params['extract_xpath'], 'xpath_attr' => $field_params['extract_xpath_attr'], 'xpath_attr_custom' => $field_params['extract_xpath_attr_custom']), $field_html);
                break;
            case 'json':
                $val = $this->field_module_json(array('json' => $field_params['extract_json'], 'json_arr' => $field_params['extract_json_arr'], 'json_arr_implode' => $field_params['extract_json_arr_implode']), $field_html);
                break;
        }
        return $val;
    }

    public function processField($fieldVal, $process)
    {
        if (empty($fieldVal) || empty($process)) {
            return $fieldVal;
        }
        foreach ($process as $params) {
            if ('html' == $params['module']) {
                $htmlAllow = array_filter(explode(',', $params['html_allow']));
                $htmlFilter = array_filter(explode(',', $params['html_filter']));
                if (!empty($htmlAllow)) {
                    $htmlAllowStr = '';
                    foreach ($htmlAllow as $v) {
                        $htmlAllowStr .= '<' . $v . '>';
                    }
                    $fieldVal = strip_tags($fieldVal, $htmlAllowStr);
                }
                if (!empty($htmlFilter)) {
                    if (in_array('all', $htmlFilter)) {
                        $fieldVal = $this->filter_html_tags($fieldVal, array('style', 'script', 'object'));
                        $fieldVal = strip_tags($fieldVal);
                    } else {
                        $fieldVal = $this->filter_html_tags($fieldVal, $htmlFilter);
                    }
                }
            } elseif ('replace' == $params['module']) {
                $fieldVal = preg_replace('/' . $params['replace_from'] . '/i', $params['replace_to'], $fieldVal);
            } elseif ('filter' == $params['module']) {
                if (!empty($params['filter_list'])) {
                    $filterList = explode("\r\n", $params['filter_list']);
                    $filterList = array_filter($filterList);
                    if (!empty($params['filter_pass'])) {
                        foreach ($filterList as $filterStr) {
                            if (stripos($fieldVal, $filterStr) !== false) {
                                $fieldVal = '';
                                break;
                            }
                        }
                    } else {
                        $fieldVal = str_ireplace($filterList, $params['filter_replace'], $fieldVal);
                    }
                }
            } elseif ('tool' == $params['module']) {
                if (in_array('format', $params['tool_list'])) {
                    $fieldVal = $this->filter_html_tags($fieldVal, array('style', 'script'));
                    $fieldVal = preg_replace('/\b(style|width|height|align)\s*=\s*([\'\"])[^\<\>\'\"]+?\\2(?=\s|$|\/|>)/i', ' ', $fieldVal);
                }
                if (in_array('trim', $params['tool_list'])) {
                    $fieldVal = trim($fieldVal);
                }
                if (in_array('is_img', $params['tool_list'])) {
                    if (!empty($GLOBALS['config']['caiji']['download_img'])) {
                        $fieldVal = preg_replace('/(\bhttp[s]{0,1}\:\/\/[^\s]+)/i', '{[img]}' . "$1" . '{[/img]}', $fieldVal);
                    }
                }
            } elseif ('translate' == $params['module']) {
                if (!empty($GLOBALS['config']['translate']) && !empty($GLOBALS['config']['translate']['open'])) {
                    $fieldVal = \Common\Util\Translator::translate($fieldVal, $params['translate_from'], $params['translate_to']);
                }
            } elseif ('batch' == $params['module']) {
                static $batch_list = array();
                if (!empty($params['batch_list'])) {
                    $listMd5 = md5($params['batch_list']);
                    if (!isset($batch_list[$listMd5])) {
                        if (preg_match_all('/([^\r\n]+?)\=([^\r\n]+)/', $params['batch_list'], $mlist)) {
                            $batch_re = $mlist[1];
                            $batch_to = $mlist[2];
                            $batch_list[$listMd5] = array($batch_re, $batch_to);
                        }
                    } else {
                        $batch_re = $batch_list[$listMd5][0];
                        $batch_to = $batch_list[$listMd5][1];
                    }
                    $batch_re = is_array($batch_re) ? $batch_re : null;
                    $batch_to = is_array($batch_to) ? $batch_to : null;
                    if (!empty($batch_re) && count($batch_re) == count($batch_to)) {
                        $fieldVal = str_replace($batch_re, $batch_to, $fieldVal);
                    }
                }
            } elseif ('substr' == $params['module']) {
                $params['substr_len'] = intval($params['substr_len']);
                if ($params['substr_len'] > 0) {
                    if (mb_strlen($fieldVal, 'utf-8') > $params['substr_len']) {
                        $fieldVal = mb_substr($fieldVal, 0, $params['substr_len'], 'utf-8') . $params['substr_end'];
                    }
                }
            }
        }
        return $fieldVal;
    }

    public function setProcess($processList)
    {
        foreach ($processList as $k => $v) {
            $v['module'] = strtolower($v['module']);
            if ('html' == $v['module']) {
                $v['html_allow'] = $this->clear_tags($v['html_allow']);
                $v['html_allow'] = implode(',', $v['html_allow']);
                $v['html_filter'] = $this->clear_tags($v['html_filter']);
                $v['html_filter'] = implode(',', $v['html_filter']);
            } elseif ('filter' == $v['module']) {
                if (preg_match_all('/[^\r\n]+/', $v['filter_list'], $filterList)) {
                    $filterList = array_filter(array_unique($filterList[0]));
                    $v['filter_list'] = implode("\r\n", $filterList);
                }
                $v['filter_list'] = trim($v['filter_list']);
            }
            $processList[$k] = $v;
        }
        $processList = array_values($processList);
        return $processList;
    }

    public function initProcess($processList)
    {
        foreach ($processList as $k => $v) {
            if ('replace' == $v['module']) {
                $v['replace_from'] = preg_replace('/\\\*([\'\/])/', "\\\\$1", $v['replace_from']);
                $v['replace_from'] = str_replace('(*)', '[\s\S]*?', $v['replace_from']);
            }
            $processList[$k] = $v;
        }
        return $processList;
    }

    public function test()
    {
        $op = I('op');
        $taskData = D('Task')->getById($this->collector['task_id']);
        D('Task')->loadConfig($taskData['config']);
        if ('source_urls' == $op) {
            $source_urls = array();
            foreach ($this->config ['source_url'] as $k => $v) {
                if (empty($v)) {
                    continue;
                }
                $urls = $this->convert_source_url($v);
                if (is_array($urls)) {
                    $source_urls = array_merge($source_urls, $urls);
                } else {
                    $source_urls [] = $urls;
                }
            }
            $this->assign('source_urls', $source_urls);
            $this->assign('config', $this->config);
            $this->display('Cpattern:test_source_urls');
        } elseif ('cont_urls' == $op) {
            $source_url = I('source_url', '', 'trim');
            $curLevel = I('level', 0, 'intval');
            $curLevel = $curLevel > 0 ? $curLevel : 0;
            $levelData = $this->get_level_urls($source_url, $curLevel);
            $this->success(array('urls' => $levelData['urls'], 'levelName' => $levelData['levelName'], 'nextLevel' => $levelData['nextLevel']));
        } elseif ('cont_url' == $op) {
            $GLOBALS['content_header'] = '测试抓取';
            $GLOBALS['breadcrumb'] = breadcrumb('测试抓取');
            $cont_url = I('cont_url', '', 'trim');
            $test = I('test');
            $url_post = $this->config['url_post'];
            $this->assign('cont_url', $cont_url);
            $this->assign('url_post', $url_post);
            $this->assign('test', $test);
            $this->display('Cpattern:test_cont_url' . (IS_AJAX ? '_ajax' : ''));
        } elseif (in_array($op, array('get_fields', 'get_paging_urls', 'get_relation_urls', 'get_html'))) {
            $cont_url = I('cont_url', '', 'trim');
            if (!preg_match('/^\w+\:\/\//', $cont_url)) {
                $cont_url = 'http://' . $cont_url;
            }
            $html = 'get_fields' == $op ? '' : $this->get_html($cont_url, false, $this->config['url_post']);
            if ('get_fields' == $op) {
                $val_list = $this->getFields($cont_url);
                if (empty($this->first_loop_field)) {
                    $val_list = array($val_list);
                }
                foreach ($val_list as $v_k => $vals) {
                    foreach ($vals as $k => $v) {
                        $vals[$k] = $v['value'];
                    }
                    $val_list[$v_k] = $vals;
                }
                $this->success($val_list);
            } elseif ('get_paging_urls' == $op) {
                $paging_urls = $this->getPagingUrls($cont_url, $html, true);
                if (empty($paging_urls)) {
                    $this->error('没有抓取到分页链接');
                } else {
                    $this->success($paging_urls);
                }
            } elseif ('get_html' == $op) {
                if (empty($html)) {
                    exit('没有抓取到源码');
                } else {
                    exit($html);
                }
            } elseif ('get_relation_urls' == $op) {
                $url_list = array();
                foreach ($this->config['new_relation_urls'] as $k => $v) {
                    $url_list[$v['name']] = $this->getRelationUrl($v['name'], $cont_url, $html);
                }
                if (empty($url_list)) {
                    $this->error('没有关联页');
                } else {
                    $this->success($url_list);
                }
            }
        } elseif ('match' == $op) {
            $GLOBALS['content_header'] = '模拟匹配';
            $GLOBALS['breadcrumb'] = breadcrumb('模拟匹配');
            if (IS_POST) {
                $type = strtolower(I('type'));
                $content = I('content', '', 'trim');
                $match = I('match', '', 'trim');
                if (empty($content)) {
                    $this->error('请输入网址或内容');
                }
                if (empty($match)) {
                    $this->error('请输入规则');
                }
                if (preg_match('/^\w+\:\/\//', $content)) {
                    $content = $this->get_html($content);
                }
                if (empty($content)) {
                    $this->error('内容空');
                }
                $val = '';
                switch ($type) {
                    case 'rule':
                        $match = $this->convert_sign_match($match);
                        $match = preg_replace('/\\\*([\'\/])/', "\\\\$1", $match);
                        $match = str_replace('(*)', '[\s\S]*?', $match);
                        $rule_merge = $this->set_merge_default($match, '');
                        $val = $this->field_module_rule(array('reg_rule' => $match, 'rule_merge' => $rule_merge), $content);
                        if (empty($val)) {
                            if (preg_match('/' . $match . '/i', $content, $val)) {
                                $val = $val[0];
                            }
                        }
                        break;
                    case 'xpath':
                        $val = $this->field_module_xpath(array('xpath' => $match, 'xpath_attr' => ''), $content);
                        break;
                    case 'json':
                        $val = $this->field_module_json(array('json' => $match), $content);
                        break;
                }
                if (empty($val)) {
                    $this->error('没有获取到数据');
                } else {
                    $this->success($val);
                }
            } else {
                $this->display('Cpattern:test_match' . (IS_AJAX ? '_ajax' : ''));
            }
        }
    }

    public function get_level_urls($source_url, $curLevel = 1)
    {
        $curLevel = $curLevel > 0 ? $curLevel : 0;
        if ($curLevel > 0) {
            $nextLevel = 0;
            if (!empty($this->config['level_urls'])) {
                if (!empty($this->config['level_urls'][$curLevel - 1])) {
                    if (!empty($this->config['level_urls'][$curLevel])) {
                        $nextLevel = $curLevel + 1;
                    }
                }
            }
            $cont_urls = $this->getLevelUrls($source_url, $curLevel);
        } else {
            $cont_urls = $this->getContUrls($source_url);
        }
        return array('urls' => $cont_urls, 'levelName' => $this->config['level_urls'][$curLevel - 1]['name'], 'nextLevel' => $nextLevel);
    }

    public function _collect_unused_cont_urls($cont_urls = array(), $echo_str = '')
    {
        $mcollected = D('Collected');
        $count_conts = count($cont_urls);
        $db_cont_urls = $mcollected->getUrlByUrl($cont_urls);
        $unused_cont_urls = array();
        $count_used = 0;
        foreach ($cont_urls as $cont_url) {
            if (array_key_exists(md5($cont_url), $this->used_cont_urls)) {
                $count_used++;
            } elseif (in_array($cont_url, $db_cont_urls)) {
                $count_used++;
            } else {
                $unused_cont_urls[md5($cont_url)] = $cont_url;
            }
        }
        if ($count_used > 0) {
            $count_used = min(count($cont_urls), $count_used);
            $this->echo_msg($echo_str . '采集到' . $count_conts . '条网址，<span style="color:orange">' . $count_used . '</span>条重复，<span style="color:green">' . (count($unused_cont_urls)) . '</span>条有效', 'black');
        } else {
            $this->echo_msg($echo_str . '采集到<span style="color:green">' . $count_conts . '</span>条有效网址', 'black');
        }
        return $unused_cont_urls;
    }

    public function _collect_level($source_url, $level = 1)
    {
        $end_echo = '</div>';
        $level = max(1, $level);
        $level_str = '';
        for ($i = 1; $i < $level; $i++) {
        }
        $next_level_str = $level_str;
        if ($level <= 1) {
            $this->cur_level_urls = array();
        }
        $this->echo_msg('', '', true, '<div style="padding-left:30px;">');
        $level_data = $this->get_level_urls($source_url, $level);
        $this->echo_msg($level_str . '抓取到' . $level . '级“' . $this->config['level_urls'][$level - 1]['name'] . '”网址' . count($level_data['urls']) . '条', 'black');
        $mcollected = D('Collected');
        $mcacheLevel = new CacheModel('level_url');
        if (!empty($level_data['urls'])) {
            $level_urls = array();
            foreach ($level_data['urls'] as $level_url) {
                $level_urls["level_{$level}:{$level_url}"] = $level_url;
            }
            $level_interval = $GLOBALS['config']['caiji']['interval'] * 60;
            $time_interval_list = array();
            $cacheLevels = $mcacheLevel->where(array('cname' => array('in', array_map('md5', array_keys($level_urls)))))->select(array('index' => 'cname,dateline'));
            if (!empty($cacheLevels)) {
                $count_db_used = 0;
                $sortLevels = array('undb' => array(), 'db' => array());
                foreach ($level_urls as $level_key => $level_url) {
                    $md5_level_key = md5($level_key);
                    if (!isset($cacheLevels[$md5_level_key])) {
                        $sortLevels['undb'][$level_key] = $level_url;
                    } else {
                        $time_interval = abs(NOW_TIME - $cacheLevels[$md5_level_key]);
                        if ($time_interval < $level_interval) {
                            $this->used_level_urls[$level_key] = 1;
                            $count_db_used++;
                            $time_interval_list[] = $time_interval;
                        } else {
                            $sortLevels['db'][$level_key] = $level_url;
                        }
                    }
                }
                if ($count_db_used > 0) {
                    $this->echo_msg($level_str . $count_db_used . '条已采集网址被过滤，下次采集需等待' . ($level_interval - max($time_interval_list)) . '秒，<a href="' . U('Admin/Setting/caiji') . '" target="_blank">设置间隔</a>', 'black');
                    if (count($level_urls) <= $count_db_used) {
                        $this->echo_msg($level_str . $level . '级“' . $this->config['level_urls'][$level - 1]['name'] . '”网址采集完毕！', 'green', true, $end_echo);
                        return 'completed';
                    }
                }
                $level_urls = array_merge($sortLevels['undb'], $sortLevels['db']);
                unset($sortLevels);
                unset($cacheLevels);
            }
            $level_data['urls'] = $level_urls;
        }
        $finished_source = true;
        $cur_level_i = 0;;
        foreach ($level_data['urls'] as $level_key => $level_url) {
            $cur_level_i++;
            if (array_key_exists($level_key, $this->used_level_urls)) {
                continue;
            }
            $this->cur_level_urls[$this->config['level_urls'][$level - 1]['name']] = $level_url;
            $this->echo_msg("{$next_level_str}分析第{$level}级：<a href='{$level_url}' target='_blank'>{$level_url}</a>", 'black');
            if ($level_data['nextLevel'] > 0) {
                $return_msg = $this->_collect_level($level_url, $level_data['nextLevel']);
                if ($return_msg == 'completed') {
                    $this->echo_msg('', '', true, $end_echo);
                    return $return_msg;
                }
            } else {
                $cont_urls = $this->getContUrls($level_url);
                $cont_urls = $this->_collect_unused_cont_urls($cont_urls, $next_level_str);
                $this->cont_urls_list[$level_key] = $cont_urls;
                $this->_collect_fields($next_level_str);
            }
            if ($this->collect_num > 0) {
                if (count($this->collected_field_list) >= $this->collect_num) {
                    if ($cur_level_i < count($level_data['urls'])) {
                        $finished_source = false;
                    }
                    break;
                }
            }
        }
        if ($finished_source) {
            $source_key = 'level_' . ($level - 1) . ':' . $source_url;
            $this->used_level_urls[$source_key] = 1;
            $mcacheLevel->set(md5($source_key), $source_key);
            if ($level <= 1) {
                $mcacheSource = new CacheModel('source_url');
                $this->used_source_urls[$source_url] = 1;
                $mcacheSource->set(md5($source_url), $source_url);
            }
        }
        $this->echo_msg('', '', true, $end_echo);
    }

    public function _collect_fields($echo_str = '')
    {
        $mcollected = D('Collected');
        $mcacheSource = new CacheModel('source_url');
        $mcacheLevel = new CacheModel('level_url');
        foreach ($this->cont_urls_list as $cont_key => $cont_urls) {
            $source_type = 0;
            if ('_source_is_url_' == $cont_key) {
                $source_type = 0;
            } elseif (strpos($cont_key, 'level_') === 0) {
                $source_type = 2;
            } else {
                $source_type = 1;
            }
            if ($source_type == 2) {
                if (array_key_exists($cont_key, $this->used_level_urls)) {
                    continue;
                }
            } else {
                if (array_key_exists($cont_key, $this->used_source_urls)) {
                    continue;
                }
            }
            $finished_cont = true;
            $cur_c_i = 0;
            foreach ($cont_urls as $cont_url) {
                $cur_c_i += 1;
                $md5_cont_url = md5($cont_url);
                if (array_key_exists($md5_cont_url, $this->used_cont_urls)) {
                    continue;
                }
                if ($mcollected->getCountByUrl($cont_url) <= 0) {
                    if (!empty($this->collected_field_list)) {
                        $this->set_html_interval();
                    }
                    $this->echo_msg($echo_str . "采集内容页：<a href='{$cont_url}' target='_blank'>{$cont_url}</a>", 'black');
                    $field_vals_list = $this->getFields($cont_url);
                    $is_loop = empty($this->first_loop_field) ? false : true;
                    if (!empty($field_vals_list)) {
                        $is_real_time = false;
                        if (!empty($GLOBALS['config']['caiji']['real_time']) && !empty($GLOBALS['real_time_release'])) {
                            $is_real_time = true;
                        }
                        if (!$is_loop) {
                            $field_vals_list = array($field_vals_list);
                        } else {
                            $loop_cont_urls = array();
                            foreach ($field_vals_list as $k => $field_vals) {
                                $loop_cont_urls[$k] = $cont_url . '#' . md5(serialize($field_vals));
                            }
                            if (!empty($loop_cont_urls)) {
                                $loop_exists_urls = $mcollected->getUrlByUrl($loop_cont_urls);
                                if (!empty($loop_exists_urls)) {
                                    $loop_exists_urls = array_flip($loop_exists_urls);
                                    foreach ($loop_cont_urls as $k => $loop_cont_url) {
                                        if (isset($loop_exists_urls[$loop_cont_url])) {
                                            unset($field_vals_list[$k]);
                                        }
                                    }
                                    $field_vals_list = array_values($field_vals_list);
                                    $this->echo_msg($echo_str . '已过滤' . count($loop_exists_urls) . '条重复数据', 'black');
                                }
                            }
                        }
                        foreach ($field_vals_list as $field_vals) {
                            $collected_data = array('url' => $cont_url, 'fields' => $field_vals);
                            if ($is_loop) {
                                $collected_data['url'] .= '#' . md5(serialize($field_vals));
                            }
                            $collected_error = '';
                            if (!empty($this->config['field_title'])) {
                                $collected_data['title'] = $field_vals[$this->config['field_title']]['value'];
                            }
                            if (!empty($collected_data['title'])) {
                                if ($mcollected->getCountByTitle($collected_data['title']) > 0) {
                                    $collected_error = '标题重复：' . mb_substr($collected_data['title'], 0, 300, 'utf-8');
                                }
                            }
                            if (empty($collected_error)) {
                                if ($is_real_time) {
                                    $GLOBALS['real_time_release']->export(array($collected_data));
                                    unset($collected_data['fields']);
                                    unset($collected_data['title']);
                                }
                                $this->collected_field_list[] = $collected_data;
                            } else {
                                A('ReleaseBase', 'Event')->record_collected($collected_data['url'], array('id' => 0, 'error' => $collected_error), array('task_id' => $this->collector['task_id'], 'module' => ''));
                            }
                        }
                    }
                    if ($is_loop) {
                        A('ReleaseBase', 'Event')->record_collected($cont_url, array('id' => 1, 'target' => '', 'desc' => '循环入库'), array('task_id' => $this->collector['task_id'], 'module' => ''), null, false);
                    }
                }
                $this->used_cont_urls[$md5_cont_url] = 1;
                if ($this->collect_num > 0) {
                    if (count($this->collected_field_list) >= $this->collect_num) {
                        if ($cur_c_i < count($cont_urls)) {
                            $finished_cont = false;
                        }
                        break;
                    }
                }
            }
            if ($finished_cont) {
                if ($source_type == 1) {
                    $mcacheSource->set(md5($cont_key), $cont_key);
                } elseif ($source_type == 2) {
                    $mcacheLevel->set(md5($cont_key), $cont_key);
                }
                if ($source_type == 2) {
                    $this->used_level_urls[$cont_key] = 1;
                } else {
                    $this->used_source_urls[$cont_key] = 1;
                }
            }
            if ($this->collect_num > 0 && count($this->collected_field_list) >= $this->collect_num) {
                break;
            }
        }
    }

    public function collect($num = 10)
    {
        define('IS_COLLECTING', 1);
        if (!$this->show_opened_tools) {
            $opened_tools = array();
            if ($GLOBALS['config']['caiji']['download_img']) {
                $opened_tools[] = '图片本地化';
            }
            if ($GLOBALS['config']['proxy']['open']) {
                $opened_tools[] = '代理';
            }
            if (!empty($opened_tools)) {
                $this->echo_msg('开启功能：' . implode('、', $opened_tools), 'black');
            }
            $this->show_opened_tools = true;
        }
        $this->collect_num = $num;
        $this->collected_field_list = array();
        $source_is_url = intval($this->config['source_is_url']);
        if (!isset($this->original_source_urls)) {
            $this->original_source_urls = array();
            foreach ($this->config ['source_url'] as $k => $v) {
                if (empty($v)) {
                    continue;
                }
                $return_s_urls = $this->convert_source_url($v);
                if (is_array($return_s_urls)) {
                    foreach ($return_s_urls as $r_s_u) {
                        $this->original_source_urls[md5($r_s_u)] = $r_s_u;
                    }
                } else {
                    $this->original_source_urls[md5($return_s_urls)] = $return_s_urls;
                }
            }
        }
        if (empty($this->original_source_urls)) {
            $this->echo_msg('没有起始页网址！');
            return 'completed';
        }
        if ($source_is_url) {
            if (isset($this->used_source_urls['_source_is_url_'])) {
                $this->echo_msg('所有起始页采集完毕！', 'green');
                return 'completed';
            }
        } else {
            if (count($this->original_source_urls) <= count($this->used_source_urls)) {
                $this->echo_msg('所有起始页采集完毕！', 'green');
                return 'completed';
            }
        }
        $source_interval = $GLOBALS['config']['caiji']['interval'] * 60;
        $time_interval_list = array();
        $source_urls = array();
        $mcacheSource = new CacheModel('source_url');
        if ($source_is_url) {
            $source_urls = $this->original_source_urls;
        } else {
            $cacheSources = $mcacheSource->where(array('cname' => array('in', array_keys($this->original_source_urls))))->select(array('index' => 'cname,dateline'));
            if (!empty($cacheSources)) {
                $count_db_used = 0;
                $sortSources = array('undb' => array(), 'db' => array());
                foreach ($this->original_source_urls as $sKey => $sVal) {
                    if (!isset($cacheSources[$sKey])) {
                        $sortSources['undb'][$sKey] = $sVal;
                    } else {
                        $time_interval = abs(NOW_TIME - $cacheSources[$sKey]);
                        if ($time_interval < $source_interval) {
                            $this->used_source_urls[$sVal] = 1;
                            $count_db_used++;
                            $time_interval_list[] = $time_interval;
                        } else {
                            $sortSources['db'][$sKey] = $sVal;
                        }
                    }
                }
                if ($count_db_used > 0) {
                    $this->echo_msg($count_db_used . '条已采集起始网址被过滤，下次采集需等待' . ($source_interval - max($time_interval_list)) . '秒，<a href="' . U('Admin/Setting/caiji') . '" target="_blank">设置间隔</a>', 'black');
                    if (count($this->original_source_urls) <= count($this->used_source_urls)) {
                        $this->echo_msg('所有起始页采集完毕！', 'green');
                        return 'completed';
                    }
                }
                $source_urls = array_merge($sortSources['undb'], $sortSources['db']);
                unset($sortSources);
                unset($cacheSources);
            } else {
                $source_urls = $this->original_source_urls;
            }
        }
        $mcollected = D('Collected');
        if ($source_is_url) {
            $this->cont_urls_list['_source_is_url_'] = array_values($source_urls);
            $source_urls = array('_source_is_url_' => '_source_is_url_');
        }
        foreach ($source_urls as $key_source_url => $source_url) {
            $this->cur_source_url = $source_url;
            if (array_key_exists($source_url, $this->used_source_urls)) {
                continue;
            }
            if ($source_is_url) {
                $this->echo_msg("起始页已转换为内容页网址", 'black');
            } else {
                $this->echo_msg("采集起始页：<a href='{$source_url}' target='_blank'>{$source_url}</a>", 'green');
            }
            if ($source_is_url) {
                $this->_collect_fields();
            } else {
                if (!empty($this->config['level_urls'])) {
                    $this->echo_msg('开始分析多级网址', 'black');
                    $return_msg = $this->_collect_level($source_url, 1);
                    if ($return_msg == 'completed') {
                        return $return_msg;
                    }
                } else {
                    $cont_urls = $this->getContUrls($source_url);
                    $this->cont_urls_list[$source_url] = $this->_collect_unused_cont_urls($cont_urls);
                    $this->_collect_fields();
                }
            }
            if ($this->collect_num > 0 && count($this->collected_field_list) >= $this->collect_num) {
                break;
            }
        }
        return $this->collected_field_list;
    }

    public function set_merge_default($reg, $merge)
    {
        if (empty($merge)) {
            $merge = '';
            if (!empty($reg)) {
                if (preg_match_all('/\<match(?P<num>\d*)\>/i', $reg, $match_signs)) {
                    foreach ($match_signs['num'] as $snum) {
                        $merge .= cp_sign('match', $snum);
                    }
                }
            }
        }
        return $merge;
    }

    public function convert_source_url($url)
    {
        $urls = array();
        if (preg_match('/\{param\:(?P<type>[a-z]+)\,(?P<val>.*?)\}/i', $url, $match)) {
            $fmtUrl = preg_replace('/\{param\:.*?\}/i', '__set:param__', $url);
            $type = strtolower($match['type']);
            $val = explode("\t", $match['val']);
            if ($type == 'num') {
                $num_start = intval($val[0]);
                $num_end = intval($val[1]);
                $num_end = max($num_start, $num_end);
                $num_inc = max(1, intval($val[2]));
                $num_desc = $val[3] ? 1 : 0;
                if ($num_desc) {
                    for ($i = $num_end; $i >= $num_start; $i--) {
                        $urls[] = str_replace('__set:param__', $num_start + ($i - $num_start) * $num_inc, $fmtUrl);
                    }
                } else {
                    for ($i = $num_start; $i <= $num_end; $i++) {
                        $urls[] = str_replace('__set:param__', $num_start + ($i - $num_start) * $num_inc, $fmtUrl);
                    }
                }
            } elseif ($type == 'letter') {
                $letter_start = ord($val[0]);
                $letter_end = ord($val[1]);
                $letter_end = max($letter_start, $letter_end);
                $letter_desc = $val[2] ? 1 : 0;
                if ($letter_desc) {
                    for ($i = $letter_end; $i >= $letter_start; $i--) {
                        $urls[] = str_replace('__set:param__', chr($i), $fmtUrl);
                    }
                } else {
                    for ($i = $letter_start; $i <= $letter_end; $i++) {
                        $urls[] = str_replace('__set:param__', chr($i), $fmtUrl);
                    }
                }
            } elseif ($type == 'custom') {
                foreach ($val as $v) {
                    $urls[] = str_replace('__set:param__', $v, $fmtUrl);
                }
            }
            return $urls;
        } elseif (preg_match('/[\r\n]/', $url)) {
            if (preg_match_all('/^\w+\:\/\/[^\r\n]+/im', $url, $urls)) {
                $urls = array_unique($urls[0]);
                $urls = array_values($urls);
            }
            return $urls;
        } else {
            return $url;
        }
    }

    public function convert_sign_wildcard($str)
    {
        return str_replace(L('sign_wildcard'), '[\s\S]*?', $str);
    }

    public function convert_sign_match($str)
    {
        $str = preg_replace('/\(\?<(content|match)/i', '(?P<match', $str);
        $sign_match = $this->sign_addslashes(cp_sign('match', '(?P<num>\d*)'));
        $str = preg_replace_callback('/(\={0,1})(\s*)([\'\"]{0,1})' . $sign_match . '\3/', function ($matches) {
            $ruleStr = $matches[1] . $matches[2] . $matches[3] . '(?P<match' . $matches['num'] . '>';
            if (!empty($matches[1]) && !empty($matches[3])) {
                $ruleStr .= '[^\<\>]*?)';
            } else {
                $ruleStr .= '[\s\S]*?)';
            }
            $ruleStr .= $matches[3];
            return $ruleStr;
        }, $str);
        return $str;
    }

    public function sign_addslashes($str)
    {
        $str = str_replace(array('[', ']'), array('\[', '\]'), $str);
        return $str;
    }

    public function filter_html_tags($content, $tags)
    {
        $tags = $this->clear_tags($tags);
        $arr1 = $arr2 = array();
        foreach ($tags as $tag) {
            $tag = strtolower($tag);
            if ($tag == 'script' || $tag == 'style' || $tag == 'object') {
                $arr1[$tag] = $tag;
            } else {
                $arr2[$tag] = $tag;
            }
        }
        if ($arr1) {
            $content = preg_replace('/<(' . implode('|', $arr1) . ')[^<>]*>[\s\S]*?<\/\1>/i', '', $content);
        }
        if ($arr2) {
            $content = preg_replace('/<[\/]*(' . implode('|', $arr2) . ')[^<>]*>/i', '', $content);
        }
        return $content;
    }

    public function clear_tags($tags)
    {
        if (!is_array($tags)) {
            $tags = preg_replace('/[\s\,\x{ff0c}]+/u', ',', $tags);
            $tags = explode(',', $tags);
        }
        if (!empty($tags) && is_array($tags)) {
            $tags = array_filter($tags);
            $tags = array_unique($tags);
            $tags = array_values($tags);
        } else {
            $tags = array();
        }
        return $tags;
    }

    public function get_html($url, $open_cache = false, $is_post = false)
    {
        if ($open_cache && !empty($this->html_cache_list[$url])) {
            return $this->html_cache_list[$url];
        }
        $headers = array();
        $options = array();
        if ($this->config['request_headers']['open']) {
            if (!empty($this->config['request_headers']['useragent'])) {
                $options['useragent'] = $this->config['request_headers']['useragent'];
            }
            if (!empty($this->config['request_headers']['cookie'])) {
                $headers['cookie'] = $this->config['request_headers']['cookie'];
            }
            if (!empty($this->config['request_headers']['referer'])) {
                $headers['referer'] = $this->config['request_headers']['referer'];
            }
            if (!empty($this->config['request_headers']['custom_names'])) {
                foreach ($this->config['request_headers']['custom_names'] as $k => $v) {
                    if (!empty($v)) {
                        $headers[$v] = $this->config['request_headers']['custom_vals'][$k];
                    }
                }
            }
        }
        $mproxy = D('Proxyip');
        if (!empty($GLOBALS['config']['proxy']['open'])) {
            $proxy_ip = $mproxy->get_usable_ip();
            $proxyIp = $mproxy->get_ip($proxy_ip);
            if (!empty($proxyIp)) {
                $options['proxy'] = $proxyIp;
            }
        }
        if ($is_post) {
            $urlQ = strpos($url, '?');
            if ($urlQ !== false) {
                $urlQ = substr($url, $urlQ + 1);
                $url = preg_replace('/\?.*$/', '', $url);
            } else {
                $urlQ = '';
            }
            $html = parent::get_html($url, $headers, $options, $this->config['charset'], $urlQ);
        } else {
            $html = parent::get_html($url, $headers, $options, $this->config['charset']);
        }
        if ($html == null) {
            $mproxy->set_ip_failed($proxy_ip);
            return null;
        }
        if ($this->config['url_complete']) {
            $base_url = $this->match_base_url($url, $html);
            $domain_url = $this->match_domain_url($url, $html);
            $html = preg_replace_callback('/(?<=\bhref\=[\'\"])([^\'\"]*)(?=[\'\"])/i', function ($matche) use ($base_url, $domain_url) {
                return CpatternEvent::create_complete_url($matche[1], $base_url, $domain_url);
            }, $html);
            $html = preg_replace_callback('/(?<=\bsrc\=[\'\"])([^\'\"]*)(?=[\'\"])/i', function ($matche) use ($base_url, $domain_url) {
                return CpatternEvent::create_complete_url($matche[1], $base_url, $domain_url);
            }, $html);
        }
        if ($open_cache) {
            $this->html_cache_list[$url] = $html;
        }
        return $html;
    }
} ?>