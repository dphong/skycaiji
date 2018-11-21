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

use Think\Storage;

if (!defined('IN_SKYCAIJI')) {
    exit('NOT IN SKYCAIJI');
}

class RfileEvent extends ReleaseEvent
{
    public function setConfig($config)
    {
        $file = I('file/a');
        $file['path'] = trim($file['path'], '\/\\');
        if (empty($file['path'])) {
            $this->error('请输入文件存放目录');
        }
        if (!preg_match('/^[a-zA-Z0-9\-\_]+$/i', $file['path'])) {
            $this->error('目录只能由字母、数字、下划线组成');
        }
        if (empty($file['type'])) {
            $this->error('请选择文件格式');
        }
        $config['file'] = $file;
        return $config;
    }

    public function export($collFieldsList, $options = null)
    {
        if (!in_array($this->config['file']['type'], array('xlsx', 'xls', 'txt'))) {
            $this->echo_msg('不支持的文件格式：' . $this->config['file']['type']);
        }
        $filepath = C('ROOTPATH') . '/data/' . $this->config['file']['path'] . '/' . $this->release['task_id'];
        $filename = date('Y-m-d', NOW_TIME) . '.' . $this->config['file']['type'];
        $filename = $filepath . '/' . $filename;
        $excelType = array('xlsx' => 'Excel2007', 'xls' => 'Excel5');
        if (!empty($excelType[$this->config['file']['type']])) {
            $excelType = $excelType[$this->config['file']['type']];
            if (empty($excelType)) {
                $this->echo_msg('错误的文件格式');
                exit();
            }
            vendor_autoload();
            if (!file_exists($filename)) {
                Storage::put($filename, null, 'F');
                $newPhpExcel = new \PHPExcel();
                $sheet1 = new \PHPExcel_Worksheet($newPhpExcel, 'Sheet1');
                $newPhpExcel->addSheet($sheet1);
                $newPhpExcel->setActiveSheetIndex(0);
                $firstFields = reset($collFieldsList);
                $firstFields = array_keys($firstFields['fields']);
                foreach ($firstFields as $k => $v) {
                    $newPhpExcel->getActiveSheet()->setCellValue(chr(65 + $k) . '1', $v);
                }
                $newWriter = \PHPExcel_IOFactory::createWriter($newPhpExcel, $excelType);
                $newWriter->save($filename);
                unset($newWriter);
                unset($newPhpExcel);
            }
            $filename = realpath($filename);
            $objReader = \PHPExcel_IOFactory::createReader($excelType);
            $phpExcel = $objReader->load($filename);
            $phpExcel->setActiveSheetIndex(0);
            $rowNum = $phpExcel->getSheet(0)->getHighestRow();
            $rowNum = intval($rowNum);
            $addedNum = 0;
            foreach ($collFieldsList as $collFieldsKey => $collFields) {
                $addedNum++;
                $curRow = $rowNum + $addedNum;
                $contUrl = $collFields['url'];
                $collFields = array_values($collFields['fields']);
                foreach ($collFields as $k => $v) {
                    $phpExcel->getActiveSheet()->setCellValue(chr(65 + $k) . $curRow, $this->get_field_val($v));
                }
                $this->record_collected($contUrl, array('id' => 1, 'target' => $filename, 'desc' => '行：' . $curRow), $this->release, $collFields['title']);
                unset($collFieldsList[$collFieldsKey]['fields']);
            }
            $objWriter = \PHPExcel_IOFactory::createWriter($phpExcel, $excelType);
            $objWriter->save($filename);
        } elseif ('txt' == $this->config['file']['type']) {
            $txtLine = 0;
            if (file_exists($filename)) {
                $fpTxt = fopen($filename, 'r');
                while (!feof($fpTxt)) {
                    if ($fpData = fread($fpTxt, 1024 * 1024 * 2)) {
                        $txtLine += substr_count($fpData, "\r\n");
                    }
                }
                fclose($fpTxt);
            } else {
                Storage::put($filename, null, 'F');
            }
            foreach ($collFieldsList as $collFieldsKey => $collFields) {
                $addedNum++;
                $fieldVals = array();
                foreach ($collFields['fields'] as $k => $v) {
                    $fieldVal = str_replace(array("\r", "\n"), array('\r', '\n'), $this->get_field_val($v));
                    if (empty($this->config['file']['txt_implode'])) {
                        $fieldVal = str_replace("\t", ' ', $fieldVal);
                    }
                    $fieldVals[] = $fieldVal;
                }
                $fieldVals = implode($this->config['file']['txt_implode'] ? $this->config['file']['txt_implode'] : "\t", $fieldVals);
                if (file_put_contents($filename, $fieldVals . "\r\n", FILE_APPEND)) {
                    $txtLine++;
                    $this->record_collected($collFields['url'], array('id' => 1, 'target' => $filename, 'desc' => '行：' . $txtLine), $this->release, $collFields['title']);
                }
                unset($collFieldsList[$collFieldsKey]['fields']);
            }
        }
        return $addedNum;
    }
} ?>