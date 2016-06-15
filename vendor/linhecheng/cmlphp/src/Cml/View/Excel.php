<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.51beautylife.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-8 下午3:07
 * @version  2.5
 * cml框架 视图 Excel渲染引擎
 * *********************************************************** */
namespace Cml\View;

use Cml\Plugin;

/**
 * 视图 Excel渲染引擎
 *
 * @package Cml\View
 */
class Excel extends Base
{
    /**
     * 文档头
     *
     * @var string
     */
    private $header = "<?xml version=\"1.0\" encoding=\"%s\"?\>\n<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:html=\"http://www.w3.org/TR/REC-html40\">";

    /**
     * 编码
     *
     * @var string
     */
    private $coding;

    /**
     * 转换类型
     *
     * @var string
     */
    private $type;

    /**
     * 表标题
     *
     * @var string
     */
    private $tWorksheetTitle;

    /**
     * 文件名
     *
     * @var string
     */
    private $filename;

    /**
     * Excel基础配置
     *
     * @param string $coding 编码
     * @param boolean $boolean 转换类型
     * @param string $title 表标题
     * @param string $filename Excel文件名
     *
     * @return void
     */
    private function config($enCoding, $boolean, $title, $filename) {
        //编码
        $this->coding = $enCoding;
        //转换类型
        if ($boolean == true){
            $this->type = 'Number';
        }else{
            $this->type = 'String';
        }
        //表标题
        $title = preg_replace('/[\\\|:|\/|\?|\*|\[|\]]/', '', $title);
        $title = substr ($title, 0, 30);
        $this->tWorksheetTitle = $title;
        //文件名
        $filename = preg_replace('/[^aA-zZ0-9\_\-]/', '', $filename);
        $this->filename = $filename;
    }
    /**
     * 循环生成Excel行
     *
     * @param array $data
     *
     * @return string
     */
    private function addRow($data) {
        $cells='';
        foreach ($data as $val){
            $type = $this->type;
            //字符转换为 HTML 实体
            $val = htmlentities($val,ENT_COMPAT,$this->coding);
            $cells .= "<Cell><Data ss:Type=\"$type\">" . $val . "</Data></Cell>\n";
        }
        return $cells;
    }
    /**
     * 生成Excel文件
     *
     * @param string $filename
     *
     * @return void
     */
    public function display($filename = '') {
        $filename == '' && $filename = 'excel';
        $this->config('utf-8', false, 'default', $filename);

        header("Content-Type: application/vnd.ms-excel; charset=" . $this->coding);
        header("Content-Disposition: inline; filename=\"" . $this->filename . ".xls\"");
        /*打印*/
        echo stripslashes (sprintf($this->header, $this->coding));
        echo "\n<Worksheet ss:Name=\"" . $this->tWorksheetTitle . "\">\n<Table>\n";
        foreach ($this->args as $val){
            $rows=$this->addRow($val);
            echo "<Row>\n".$rows."</Row>\n";
        }
        echo "</Table>\n</Worksheet>\n";
        echo "</Workbook>";
        exit();
    }
}