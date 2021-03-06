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

define('IN_SKYCAIJI', 1);
define('SKYCAIJI_VERSION', '1.3');
if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}
function bootstrap_pages($pageHtml)
{
    if ($pageHtml) {
        $pageHtml = str_replace('<div>', '<nav><ul class="pagination">', $pageHtml);
        $pageHtml = str_replace('</div>', '</ul></nav>', $pageHtml);
        $pageHtml = str_replace('<span class="current">', '<li class="active"><a href="javascript:;">', $pageHtml);
        $pageHtml = str_replace('</span>', '</a></li>', $pageHtml);
        $pageHtml = preg_replace('/<a\b([^\'\"]*?)class=[\'\"][^\'\"]*?[\'\"]\s*/i', "<li><a$1", $pageHtml);
        $pageHtml = str_replace('</a>', '</a></li>', $pageHtml);
    }
    return $pageHtml;
}

function url_b64encode($string)
{
    $data = base64_encode($string);
    $data = str_replace(array('+', '/', '='), array('-', '_', ''), $data);
    return $data;
}

function url_b64decode($string)
{
    $data = str_replace(array('-', '_'), array('+', '/'), $string);
    $mod4 = strlen($data) % 4;
    if ($mod4) {
        $data .= substr('====', $mod4);
    }
    return base64_decode($data);
}

function breadcrumb($arr)
{
    $return = '';
    foreach ($arr as $v) {
        if (is_string($v)) {
            $return .= '<li>' . $v . '</li>';
        } elseif (!empty($v['url'])) {
            $return .= '<li><a href="' . $v['url'] . '">' . $v['title'] . '</a></li>';
        }
    }
    return $return;
}

function array_array_map($callback, $arr1, array $_ = null)
{
    if (is_array($arr1)) {
        foreach ($arr1 as $k => $v) {
            if (!is_array($v)) {
                $arr[$k] = call_user_func($callback, $v);
            } else {
                $arr[$k] = array_array_map($callback, $v, $_);
            }
        }
    }
    return $arr;
}

function array_implode($glue, $pieces)
{
    $str = '';
    foreach ($pieces as $v) {
        if (is_array($v)) {
            $str .= array_implode($glue, $v);
        } else {
            $str .= $glue . $v;
        }
    }
    return $str;
}

function auto_convert2utf8($str)
{
    $encode = mb_detect_encoding($str, array('ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5'));
    if (strcasecmp($encode, 'utf-8') !== 0) {
        $str = iconv($encode, 'utf-8//IGNORE', $str);
    }
    return $str;
}

function vendor_autoload()
{
    static $loaded = false;
    if (!$loaded) {
        require_once dirname(THINK_PATH) . '/vendor/autoload.php';
        $loaded = true;
    }
}

function send_mail($emailConfig, $to, $name, $subject = '', $body = '', $attachment = null)
{
    set_time_limit(0);
    vendor('phpmailer/phpmailer/PHPMailerAutoload', C('ROOTPATH') . '/vendor');
    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->Host = $emailConfig['smtp'];
    $mail->SMTPAuth = true;
    $mail->Username = $emailConfig['email'];
    $mail->Password = $emailConfig['pwd'];
    $mail->SMTPSecure = empty($emailConfig['type']) ? 'tls' : $emailConfig['type'];
    $mail->Port = $emailConfig['port'];
    $mail->setFrom($emailConfig['email'], $emailConfig['sender']);
    $mail->addAddress($to, $name);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->AltBody = '';
    if (is_array($attachment)) {
        foreach ($attachment as $file) {
            is_file($file) && $mail->AddAttachment($file);
        }
    }
    return $mail->Send() ? true : $mail->ErrorInfo;
}

function pwd_encrypt($pwd)
{
    return md5(sha1($pwd));
}

function clientinfo()
{
    $info = array('url' => (is_ssl() ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/' . trim(__ROOT__, '\/\\'), 'v' => constant('SKYCAIJI_VERSION'),);
    return $info;
}

function get_html($url, $headers = null, $options = array(), $fromEncode = 'auto', $post_data = null)
{
    $options = is_array($options) ? $options : array();
    if (!isset($options['useragent'])) {
        $options['useragent'] = 'Mozilla/5.0 (Windows NT 6.2; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1667.0 Safari/537.36';
    }
    if (!preg_match('/^\w+\:\/\//', $url)) {
        $url = 'http://' . $url;
    }
    try {
        vendor_autoload();
        if (!isset($post_data)) {
            $allow_get = true;
            if (!empty($options['max_bytes'])) {
                $max_bytes = intval($options['max_bytes']);
                unset($options['max_bytes']);
                $request = \Requests::head($url, $headers, $options);
                if (preg_match('/\bContent-Length\s*:\s*(\d+)/i', $request->raw, $contLen)) {
                    $contLen = intval($contLen[1]);
                    if ($contLen >= $max_bytes) {
                        $allow_get = false;
                    }
                }
            }
            if ($allow_get) {
                $request = \Requests::get($url, $headers, $options);
            } else {
                $request = null;
            }
        } else {
            if (!is_array($post_data)) {
                if (preg_match_all('/([^\&]+)\=([^\&]*)/', $post_data, $m_post_data)) {
                    $new_post_data = array();
                    foreach ($m_post_data[1] as $k => $v) {
                        $new_post_data[$v] = rawurldecode($m_post_data[2][$k]);
                    }
                    $post_data = $new_post_data;
                } else {
                    $post_data = '';
                }
            }
            $post_data = empty($post_data) ? array() : $post_data;
            $request = \Requests::post($url, $headers, $post_data, $options);
        }
    } catch (\Exception $e) {
        $request = null;
    }
    if (!empty($request)) {
        if (200 == $request->status_code) {
            $html = $request->body;
            if ($fromEncode == 'auto') {
                $htmlCharset = '';
                if (preg_match('/<meta[^<>]*?content=[\'\"]text\/html\;\s*charset=(?P<charset>[^\'\"\<\>]+?)[\'\"]/i', $html, $htmlCharset) || preg_match('/<meta[^<>]*?charset=[\'\"](?P<charset>[^\'\"\<\>]+?)[\'\"]/i', $html, $htmlCharset)) {
                    $htmlCharset = strtolower(trim($htmlCharset['charset']));
                    if ('utf8' == $htmlCharset) {
                        $htmlCharset = 'utf-8';
                    }
                } else {
                    $htmlCharset = '';
                }
                $headerCharset = '';
                if (preg_match('/charset=(?P<charset>[\w\-]+)/i', $request->headers['content-type'], $headerCharset)) {
                    $headerCharset = strtolower(trim($headerCharset['charset']));
                    if ('utf8' == $headerCharset) {
                        $headerCharset = 'utf-8';
                    }
                } else {
                    $headerCharset = '';
                }
                if (!empty($htmlCharset) && !empty($headerCharset) && strcasecmp($htmlCharset, $headerCharset) !== 0) {
                    $zhCharset = array('gb18030', 'gbk', 'gb2312');
                    if (in_array($htmlCharset, $zhCharset) && in_array($headerCharset, $zhCharset)) {
                        $fromEncode = 'gb18030';
                    } else {
                        $autoEncode = mb_detect_encoding($html, array('ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5'));
                        if (strcasecmp($htmlCharset, $autoEncode) == 0) {
                            $fromEncode = $htmlCharset;
                        } elseif (strcasecmp($headerCharset, $autoEncode) == 0) {
                            $fromEncode = $headerCharset;
                        } else {
                            $fromEncode = $autoEncode;
                        }
                    }
                } elseif (!empty($htmlCharset)) {
                    $fromEncode = $htmlCharset;
                } elseif (!empty($headerCharset)) {
                    $fromEncode = $headerCharset;
                } else {
                    $fromEncode = mb_detect_encoding($html, array('ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5'));
                }
                $fromEncode = empty($fromEncode) ? null : $fromEncode;
            }
            $fromEncode = trim($fromEncode);
            if (!empty($fromEncode)) {
                $fromEncode = strtolower($fromEncode);
                switch ($fromEncode) {
                    case 'utf8':
                        $fromEncode = 'utf-8';
                        break;
                    case 'cp936':
                        $fromEncode = 'gbk';
                        break;
                    case 'cp20936':
                        $fromEncode = 'gb2312';
                        break;
                    case 'cp950':
                        $fromEncode = 'big5';
                        break;
                }
                if ($fromEncode != 'utf-8') {
                    $html = iconv($fromEncode, 'utf-8//IGNORE', $html);
                }
            }
        }
    }
    return $html ? $html : null;
}

function load_data_config()
{
    static $loaded = false;
    if (!$loaded) {
        if (file_exists(C('ROOTPATH') . '/data/config.php')) {
            $dataDbConfig = include C('ROOTPATH') . '/data/config.php';
            if (!empty($dataDbConfig) && is_array($dataDbConfig)) {
                C($dataDbConfig);
                $loaded = true;
            }
        }
    }
}

function clear_dir($path, $passFiles = null)
{
    if (empty($path)) {
        return;
    }
    $path = realpath($path);
    if (empty($path)) {
        return;
    }
    $passFiles = array_map('realpath', $passFiles);
    $fileList = scandir($path);
    foreach ($fileList as $file) {
        $fileName = realpath($path . '/' . $file);
        if (is_dir($fileName) && '.' != $file && '..' != $file) {
            clear_dir($fileName, $passFiles);
            rmdir($fileName);
        } elseif (is_file($fileName)) {
            if ($passFiles && in_array($fileName, $passFiles)) {
            } else {
                unlink($fileName);
            }
        }
    }
    clearstatcache();
}

function is_collecting()
{
    if (defined('IS_COLLECTING')) {
        return true;
    } else {
        return false;
    }
} 