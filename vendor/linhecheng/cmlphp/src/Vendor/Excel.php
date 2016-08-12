<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-21 下午2:23
 * @version  2.6
 * cml框架 Excel生成类
 * *********************************************************** */
namespace Cml\Vendor;

/**
 * Excel生成类
 *
 * @package Cml\Vendor
 */
class Excel
{
    private $header = "<?xml version=\"1.0\" encoding=\"%s\"?\>\n<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:html=\"http://www.w3.org/TR/REC-html40\">";
    private $coding;
    private $type;
    private $tWorksheetTitle;
    private $filename;
    private $titleRow = array();

    /**
     * Excel基础配置
     *
     * @param string $enCoding 编码
     * @param boolean $boolean 转换类型
     * @param string $title 表标题
     * @param string $filename Excel文件名
     *
     * @return void
     */
    public function config($enCoding,$boolean,$title,$filename)
    {
        //编码
        $this->coding = $enCoding;
        //转换类型
        if ($boolean == true){
            $this->type = 'Number';
        } else {
            $this->type = 'String';
        }
        //表标题
        $title = preg_replace('/[\\\|:|\/|\?|\*|\[|\]]/', '', $title);
        $title = substr ($title, 0, 30);
        $this->tWorksheetTitle=$title;
        //文件名
        $filename = preg_replace('/[^aA-zZ0-9\_\-]/', '', $filename);
        $this->filename = $filename;
    }

    /**
     * 添加标题行
     *
     * @param array $titleArr
     */
    public function setTitleRow($titleArr)
    {
        $this->titleRow = $titleArr;
    }

    /**
     * 循环生成Excel行
     *
     * @param array $data
     *
     * @return string
     */
    private function addRow($data)
    {
        $cells = '';
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
     * @param array $data
     *
     * @return void
     */
    public function excelXls($data)
    {
        header("Content-Type: application/vnd.ms-excel; charset=" . $this->coding);
        header("Content-Disposition: inline; filename=\"" . $this->filename . ".xls\"");
        /*打印*/
        echo stripslashes (sprintf($this->header, $this->coding));
        echo "\n<Worksheet ss:Name=\"" . $this->tWorksheetTitle . "\">\n<Table>\n";

        if (is_array($this->titleRow)) {
            echo "<Row>\n".$this->addRow($this->titleRow)."</Row>\n";
        }
        foreach ($data as $val){
            $rows=$this->addRow($val);
            echo "<Row>\n".$rows."</Row>\n";
        }
        echo "</Table>\n</Worksheet>\n";
        echo "</Workbook>";
        exit();
    }
}