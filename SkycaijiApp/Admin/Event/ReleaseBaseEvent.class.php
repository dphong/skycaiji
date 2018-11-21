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

use Admin\Controller\BaseController;
use Think\Storage;

if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

class ReleaseBaseEvent extends BaseController
{
    public function record_collected($url, $returnData, $release, $title = null, $echo = true)
    {
        if ($returnData['id'] > 0) {
            D('Collected')->add(array('url' => $url, 'urlMd5' => md5($url), 'titleMd5' => empty($title) ? '' : md5($title), 'target' => $returnData['target'], 'desc' => $returnData['desc'] ? $returnData['desc'] : '', 'error' => '', 'task_id' => $release ['task_id'], 'release' => $release['module'], 'addtime' => time()));
            if (!empty($returnData['target'])) {
                $target = $returnData['target'];
                if (preg_match('/^http(s){0,1}\:\/\//i', $target)) {
                    $target = '<a href="' . $target . '" target="_blank">' . $target . '</a>';
                }
                $this->echo_msg("成功将<a href='{$url}' target='_blank'>内容</a>发布至：{$target}", 'green', $echo);
            } else {
                $this->echo_msg("成功发布：<a href='{$url}' target='_blank'>{$url}</a>", 'green', $echo);
            }
        } else {
            if (!empty($returnData['error'])) {
                D('Collected')->add(array('url' => $url, 'urlMd5' => md5($url), 'titleMd5' => '', 'target' => '', 'desc' => '', 'error' => $returnData['error'], 'task_id' => $release ['task_id'], 'release' => $release['module'], 'addtime' => time()));
                $this->echo_msg($returnData['error'] . "：<a href='{$url}' target='_blank'>{$url}</a>", 'red', $echo);
            }
        }
    }

    public function get_field_val($collFieldVal)
    {
        if (empty($collFieldVal)) {
            return '';
        }
        $val = $collFieldVal['value'];
        if (!empty($GLOBALS['config']['caiji']['download_img'])) {
            if (!empty($collFieldVal['img'])) {
                if (!is_array($collFieldVal['img'])) {
                    $collFieldVal['img'] = array($collFieldVal['img']);
                }
                $total = count($collFieldVal['img']);
                $curI = 0;
                foreach ($collFieldVal['img'] as $imgUrl) {
                    $newImgUrl = $this->download_img($imgUrl);
                    if ($newImgUrl != $imgUrl) {
                        $val = str_replace($imgUrl, $newImgUrl, $val);
                    }
                    $curI++;
                    if ($curI < $total) {
                        if (!empty($GLOBALS['config']['caiji']['img_interval'])) {
                            sleep($GLOBALS['config']['caiji']['img_interval']);
                        }
                    }
                }
            }
        }
        return $val;
    }

    public function download_img($url)
    {
        static $img_path = null;
        static $img_url = null;
        if (!isset($img_path)) {
            if (empty($GLOBALS['config']['caiji']['img_path'])) {
                $img_path = C('ROOTPATH') . '/data/images/';
            } else {
                $img_path = rtrim($GLOBALS['config']['caiji']['img_path'], '\/\\') . '/';
            }
        }
        if (!isset($img_url)) {
            if (empty($GLOBALS['config']['caiji']['img_url'])) {
                $img_url = C('ROOTURL') . '/data/images/';
            } else {
                $img_url = rtrim($GLOBALS['config']['caiji']['img_url'], '\/\\') . '/';
            }
        }
        if (empty($url)) {
            return '';
        }
        if (!preg_match('/^\w+\:\/\//', $url)) {
            return $url;
        }
        static $imgList = array();
        $key = md5($url);
        if (!isset($imgList[$key])) {
            if (preg_match('/\.(jpg|jpeg|gif|png|bmp)\b/i', $url, $prop)) {
                $prop = strtolower($prop[1]);
            } else {
                $prop = 'jpg';
            }
            $filename = '';
            $imgurl = '';
            $imgname = '';
            if ('url' == $GLOBALS['config']['caiji']['img_name']) {
                $imgname = substr($key, 0, 2) . '/' . substr($key, -2, 2) . '/';
            } else {
                $imgname = date('Y-m-d', NOW_TIME) . '/';
            }
            $imgname .= $key . '.' . $prop;
            $filename = $img_path . $imgname;
            $imgurl = $img_url . $imgname;
            if (!file_exists($filename)) {
                $mproxy = D('Proxyip');
                try {
                    $options = array();
                    if (!empty($GLOBALS['config']['caiji']['img_timeout'])) {
                        $options['timeout'] = $GLOBALS['config']['caiji']['img_timeout'];
                    }
                    if (!empty($GLOBALS['config']['proxy']['open'])) {
                        $proxy_ip = $mproxy->get_usable_ip();
                        $proxyIp = $mproxy->get_ip($proxy_ip);
                        if (!empty($proxyIp)) {
                            $options['proxy'] = $proxyIp;
                        }
                    }
                    if (!empty($GLOBALS['config']['caiji']['img_max'])) {
                        $options['max_bytes'] = intval($GLOBALS['config']['caiji']['img_max']) * 1024 * 1024;
                    }
                    $imgCode = get_html($url, null, $options, 'utf-8');
                    if (!empty($imgCode)) {
                        if (Storage::put($filename, $imgCode, 'F')) {
                            $imgList[$key] = $imgurl;
                        }
                    }
                } catch (\Exception $ex) {
                }
            } else {
                $imgList[$key] = $imgurl;
            }
        }
        return empty($imgList[$key]) ? $url : $imgList[$key];
    }

    public function utf8_to_charset($charset, $val)
    {
        static $chars = array('utf-8', 'utf8', 'utf8mb4');
        if (!in_array(strtolower($charset), $chars)) {
            if (!empty($val)) {
                $val = iconv('utf-8', $charset . '//IGNORE', $val);
            }
        }
        return $val;
    }

    public function auto_convert2utf8($arr)
    {
        $arr = array_array_map('auto_convert2utf8', $arr);
        return $arr;
    }
} ?>