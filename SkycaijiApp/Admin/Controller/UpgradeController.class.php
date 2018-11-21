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

use Think\Storage;
use Install\Event\UpgradeDbEvent;

if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

class UpgradeController extends BaseController
{
    public $oldFilePath = '';
    public $newFilePath = '';

    public function __construct()
    {
        parent::__construct();
        set_time_limit(3600);
        $this->oldFilePath = C('ROOTPATH') . '/data/program/backup/skycaiji' . $GLOBALS['config']['version'];
        $this->newFilePath = C('ROOTPATH') . '/data/program/upgrade/skycaiji' . $GLOBALS['config']['version'];
    }

    public function newVersionAction()
    {
        $version = get_html('http://www.skycaiji.com/upgrade/program/version', null, null, 'utf-8');
        $version = json_decode($version, true);
        $new_version = trim($version['new_version']);
        $cur_version = $GLOBALS['config']['version'];
        if (version_compare($new_version, $cur_version) >= 1) {
            $this->success(array('new_version' => $version['new_version']));
        } else {
            $this->error();
        }
    }

    public function downCompleteAction()
    {
        $downFileList = $this->_getNewFiles();
        $errorFiles = array();
        foreach ($downFileList as $file) {
            $filename = $this->newFilePath . $file['file'];
            if (!file_exists($filename)) {
                $errorFiles[] = $file['file'];
            } else {
                $filemd5 = md5_file($filename);
                if ($filemd5 != $file['md5']) {
                    $errorFiles[] = $file['file'];
                }
            }
        }
        if (!empty($errorFiles)) {
            $errorFiles = array_unique($errorFiles);
            $this->error($errorFiles);
        } else {
            foreach ($downFileList as $file) {
                $content = file_get_contents($this->newFilePath . $file['file']);
                Storage::put(C('ROOTPATH') . $file['file'], $content, 'F');
            }
            $upgradeDb = new UpgradeDbEvent();
            $upgradeResult = $upgradeDb->run();
            if ($upgradeResult['success']) {
                $this->success();
            } else {
                $this->error();
            }
        }
    }

    public function downFileAction()
    {
        $fileName = I('filename');
        $filemd5 = I('filemd5');
        if (file_exists($this->newFilePath . $fileName)) {
            if ($filemd5 == md5_file($this->newFilePath . $fileName)) {
                $this->success();
            }
        }
        $newFile = get_html('http://www.skycaiji.com/upgrade/program/getFile?filename=' . rawurlencode(base64_encode($fileName)), null, array('timeout' => 100), 'utf-8');
        if (!empty($newFile)) {
            $oldFile = file_get_contents(C('ROOTPATH') . $fileName);
            if (!empty($oldFile)) {
                Storage::put($this->oldFilePath . $fileName, $oldFile, 'F');
            }
            Storage::put($this->newFilePath . $fileName, $newFile, 'F');
            $newFilemd5 = md5_file($this->newFilePath . $fileName);
            if ($newFilemd5 == $filemd5) {
                $this->success();
            } else {
                $this->error('文件校验失败：' . $fileName);
            }
        }
        $this->error();
    }

    public function newFilesAction()
    {
        $downFileList = $this->_getNewFiles();
        if (empty($downFileList)) {
            $this->error();
        } else {
            $this->success(array('files' => $downFileList));
        }
    }

    public function _getNewFiles()
    {
        $md5Files = array();
        program_filemd5_list(C('ROOTPATH'), $md5Files);
        $md5FileList = array();
        foreach ($md5Files as $k => $v) {
            $md5FileList[md5($v['file'])] = $v;
        }
        unset($md5Files);
        $newFileList = get_html('http://www.skycaiji.com/upgrade/program/files', null, array('timeout' => 100), 'utf-8');
        $newFileList = json_decode($newFileList, true);
        $downFileList = array();
        foreach ($newFileList as $newFile) {
            $filenameMd5 = md5($newFile['file']);
            if (isset($md5FileList[$filenameMd5])) {
                if ($md5FileList[$filenameMd5]['md5'] != $newFile['md5'] || $md5FileList[$filenameMd5]['file'] != $newFile['file']) {
                    $downFileList[] = $newFile;
                }
            } else {
                $downFileList[] = $newFile;
            }
        }
        return empty($downFileList) ? null : $downFileList;
    }
}