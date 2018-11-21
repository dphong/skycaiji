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
use Admin\Model\BaseModel;

if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

abstract class CollectorEvent extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        vendor_autoload();
    }

    public function error($message = '', $jumpUrl = '', $ajax = false)
    {
        if (is_collecting()) {
            parent::echo_msg($message, 'red');
            return null;
        } else {
            parent::error($message, $jumpUrl, $ajax);
        }
    }

    public function echo_msg($str, $color = 'red', $echo = true, $end_str = '')
    {
        if (is_collecting()) {
            parent::echo_msg($str, $color, $echo, $end_str);
        }
    }

    public abstract function setConfig($config);

    public abstract function init($config);

    public abstract function collect($num = 10);

    public abstract function test();

    public function get_html($url, $headers = null, $options = array(), $fromEncode = 'auto', $post_data = null)
    {
        return get_html($url, $headers, $options, $fromEncode, $post_data);
    }

    public function set_html_interval()
    {
        if (is_collecting()) {
            if ($GLOBALS['config']['caiji']['html_interval'] > 0) {
                sleep($GLOBALS['config']['caiji']['html_interval']);
                BaseModel::_reset_collecting_db();
            }
        }
    }

    public function get_content($html)
    {
        try {
            $cread = new \Common\Util\Readability($html, 'utf-8');
            $data = $cread->getContent();
        } catch (\Exception $ex) {
            return null;
        }
        return trim($data['content']);
    }

    public function get_title($html)
    {
        if (preg_match_all('/<h1\b[^<>]*?>(?P<content>[\s\S]+?)<\/h1>/i', $html, $title)) {
            if (count($title['content']) > 1) {
                $title = null;
            } else {
                $title = strip_tags(reset($title['content']));
                if (preg_match('/^((\&nbsp\;)|\s)*$/i', $title)) {
                    $title = null;
                }
            }
        } else {
            $title = null;
        }
        if (empty($title)) {
            $pattern = array('<(h[12])\b[^<>]*?(id|class)=[\'\"]{0,1}[^\'\"<>]*(title|article)[^<>]*>(?P<content>[\s\S]+?)<\/\1>', '<title>(?P<content>[\s\S]+?)([\-\_\|][\s\S]+?)*<\/title>');
            $title = $this->return_preg_match($pattern, $html);
        }
        return trim(strip_tags($title));
    }

    public function get_keywords($html)
    {
        $patterns = array('<meta[^<>]*?name=[\'\"]keywords[\'\"][^<>]*?content=[\'\"](?P<content>[\s\S]*?)[\'\"]', '<meta[^<>]*?content=[\'\"](?P<content>[\s\S]*?)[\'\"][^<>]*?name=[\'\"]keywords[\'\"]');
        $data = $this->return_preg_match($patterns, $html);
        return trim(strip_tags($data));
    }

    public function get_description($html)
    {
        $patterns = array('<meta[^<>]*?name=[\'\"]description[\'\"][^<>]*?content=[\'\"](?P<content>[\s\S]*?)[\'\"]', '<meta[^<>]*?content=[\'\"](?P<content>[\s\S]*?)[\'\"][^<>]*?name=[\'\"]description[\'\"]');
        $data = $this->return_preg_match($patterns, $html);
        return trim(strip_tags($data));
    }

    public function return_preg_match($pattern, $content, $reg_key = 'content')
    {
        if (is_array($pattern)) {
            foreach ($pattern as $patt) {
                if (preg_match('/' . $patt . '/i', $content, $cont)) {
                    $cont = $cont[$reg_key];
                    break;
                } else {
                    $cont = false;
                }
            }
        } else {
            if (preg_match('/' . $pattern . '/i', $content, $cont)) {
                $cont = $cont[$reg_key];
            } else {
                $cont = false;
            }
        }
        return empty($cont) ? '' : $cont;
    }

    public function match_base_url($url, $html)
    {
        if (preg_match('/<base[^<>]*href=[\'\"](?P<base>[^\<\>\"\']*?)[\'\"]/i', $html, $base_url)) {
            $base_url = $base_url['base'];
        } else {
            $base_url = preg_replace('/[\#\?][^\/]*$/', '', $url);
            if (preg_match('/^\w+\:\/\/([\w\-]+\.){1,}[\w]+\/.+/', $base_url) && preg_match('/\.[a-z]+$/i', $base_url)) {
                $base_url = preg_replace('/\/[^\/]*\.[a-z]+$/', '', $base_url);
            }
        }
        $base_url = rtrim($base_url, '/');
        return $base_url ? $base_url : null;
    }

    public function match_domain_url($url)
    {
        if (preg_match('/^\w+\:\/\/([\w\-]+\.){1,}[\w]+/', $url, $domain_url)) {
            $domain_url = rtrim($domain_url[0], '/');
        }
        return $domain_url ? $domain_url : null;
    }

    public function create_complete_url($url, $base_url, $domain_url)
    {
        if (preg_match('/^\w+\:\/\//', $url)) {
            return $url;
        } elseif (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        } elseif (strpos($url, '/') === 0) {
            $url = $domain_url . '/' . ltrim($url, '/');
        } elseif (stripos($url, 'javascript') === 0 || stripos($url, '#') === 0) {
            $url = '';
        } elseif (!preg_match('/^\w+\:\/\//', $url)) {
            $url = $base_url . '/' . ltrim($url, '/');
        }
        return $url;
    }
} ?>