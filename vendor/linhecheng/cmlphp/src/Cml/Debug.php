<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-8 下午3:07
 * @version  2.6
 * cml框架 系统DEBUG调试类
 * *********************************************************** */
namespace Cml;

use Cml\Http\Request;

/**
 * Debug调试处理类,debug=true时负责调试相关信息的收集及ui的展示
 *
 * @package Cml
 */
class Debug
{
    private static $includefile = array();//消息的类型为包含文件
    private static $tipInfo = array();//消息的类型为普通消息
    private static $sqls = array();//消息的类型为sql
    private static $stopTime;//程序运行结束时间
    private static $startMemory;//程序开始运行所用内存
    private static $stopMemory;//程序结束运行时所用内存
    private static $tipInfoType = array(
        E_WARNING => '运行时警告',
        E_NOTICE => '运行时提醒',
        E_STRICT => '编码标准化警告',
        E_USER_ERROR => '自定义错误',
        E_USER_WARNING => '自定义警告',
        E_USER_NOTICE => '自定义提醒',
        E_DEPRECATED => '过时函数提醒',
        E_RECOVERABLE_ERROR => '可捕获的致命错误',
        'Unknow' => '未知错误'
    );


    /**
     * info类型的提示信息
     *
     * @var int
     */
    const TIP_INFO_TYPE_INFO = 0;

    /**
     * 包含文件的提示信息
     *
     * @var int
     */
    const TIP_INFO_TYPE_INCLUDE_FILE = 1;

    /**
     * SQL语句调试信息
     *
     * @var int
     */
    const TIP_INFO_TYPE_SQL = 2;

    /**
     * 正常的sql执行语句
     *
     * @var int
     */
    const SQL_TYPE_NORMAL = 1;

    /**
     * 该SQL执行结果直接从缓存返回
     *
     * @var int
     */
    const SQL_TYPE_FROM_CACHE = 2;

    /**
     * 执行过慢的sql
     *
     * @var int
     */
    const SQL_TYPE_SLOW = 3;

    /**
     * 返回执行的sql语句
     *
     * @return array
     */
    public static function getSqls()
    {
        return self::$sqls;
    }

    /**
     * 返回提示信息
     *
     * @return array
     */
    public static function getTipInfo()
    {
        return self::$tipInfo;
    }

    /**
     * 返回包含的文件
     *
     * @return array
     */
    public static function getIncludeFiles()
    {
        return self::$includefile;
    }


    /**
     * 在脚本开始处调用获取脚本开始时间的微秒值\及内存的使用量
     *
     */
    public static function start()
    {
        // 记录内存初始使用
        function_exists('memory_get_usage') && self::$startMemory = memory_get_usage();
    }

    /**
     * 程序执行完毕,打印CmlPHP运行信息并中止
     *
     */
    public static function stop()
    {
        self::$stopTime = microtime(true);
        // 记录内存结束使用
        function_exists('memory_get_usage') && self::$stopMemory = memory_get_usage();
        self::showCmlPHPConsole();
        CML_OB_START && ob_end_flush();
        exit();
    }

    /**
     * 返回程序运行所消耗时间
     *
     * @return float
     */
    public static function useTime()
    {
        return round((self::$stopTime - Cml::$nowMicroTime), 4);  //计算后以4舍5入保留4位返回
    }

    /**
     * 返回程序运行所消耗的内存
     *
     * @return string
     */
    public static function useMemory()
    {
        if (function_exists('memory_get_usage')) {
            return number_format((self::$stopMemory - self::$startMemory) / 1024, 2) . 'kb';
        } else {
            return '当前服务器环境不支持内存消耗统计';
        }
    }

    /**
     * 错误handler
     *
     * @param int $errno 错误类型 分运行时警告、运行时提醒、自定义错误、自定义提醒、未知等
     * @param string $errstr 错误提示
     * @param string $errfile 发生错误的文件
     * @param int $errline 错误所在行数
     *
     * @return void
     */
    public static function catcher($errno, $errstr, $errfile, $errline)
    {
        if (!isset(self::$tipInfoType[$errno])) {
            $errno = 'Unknow';
        }
        if ($errno == E_NOTICE || $errno == E_USER_NOTICE) {
            $color = '#000088';
        } else {
            $color = 'red';
        }
        $mess = "<span style='color:{$color}'>";
        $mess .= '<b>' . self::$tipInfoType[$errno] . "</b>[在文件 {$errfile} 中,第 $errline 行]:";
        $mess .= $errstr;
        $mess .= '</span>';
        self::addTipInfo($mess);
    }

    /**
     * 添加调试信息
     *
     * @param string $msg 调试消息字符串
     * @param int $type 消息的类型
     *
     * @return void
     */
    public static function addTipInfo($msg, $type = self::TIP_INFO_TYPE_INFO)
    {
        if (Cml::$debug) {
            switch ($type) {
                case 0:
                    self::$tipInfo[] = $msg;
                    break;
                case 1:
                    self::$includefile[] = $msg;
                    break;
                case 2:
                    self::$sqls[] = $msg;
                    break;
            }
        }
    }

    /**
     * 添加一条sql查询的调试信息
     *
     * @param $sql
     * @param int $type sql类型 参考常量声明SQL_TYPE_NORMAL、SQL_TYPE_FROM_CACHE、SQL_TYPE_SLOW
     * @param int $other type = SQL_TYPE_SLOW时带上执行时间
     */
    public static function addSqlInfo($sql, $type = self::SQL_TYPE_NORMAL, $other = 0)
    {
        switch($type) {
            case self::SQL_TYPE_FROM_CACHE:
                $sql .= "<span style='color:red;'>[from cache]</span>";
                break;
            case self::SQL_TYPE_SLOW:
                $sql .= "<span style='color:red;'>[slow sql, {$other}]</span>";
                break;
        }
        self::addTipInfo($sql, self::TIP_INFO_TYPE_SQL);
    }

    /**
     * 显示代码片段
     *
     * @param string $file 文件路径
     * @param int $focus 出错的行
     * @param int $range 基于出错行上下显示多少行
     * @param array $style 样式
     *
     * @return string
     */
    public static function codeSnippet( $file, $focus, $range = 7, $style = array('lineHeight' => 20, 'fontSize' => 13))
    {
        $html = highlight_file( $file, true );
        if (!$html) {
            return false;
        }
        // 分割html保存到数组
        $html = explode('<br />', $html);
        $lineNums = count($html);
        // 代码的html
        $codeHtml = '';

        // 获取相应范围起止索引
        $start = ($focus - $range) < 1 ? 0 : ($focus - $range -1);
        $end = ( ($focus + $range) > $lineNums ? $lineNums - 1 : ($focus + $range - 1) );

        // 修正开始标签
        // 有可能取到的片段缺少开始的span标签，而它包含代码着色的CSS属性
        // 如果缺少，片段开始的代码则没有颜色了，所以需要把它找出来
        if (substr($html[$start], 0, 5) !== '<span') {
            while ( ($start - 1) >= 0 ) {
                $match = array();
                preg_match('/<span style="color: #([\w]+)"(.(?!<\/span>))+$/', $html[--$start], $match);
                if ( !empty($match) ) {
                    $html[$start] = "<span style=\"color: #{$match[1]}\">" . $html[$start];
                    break;
                }
            }
        }

        for ( $line = $start; $line <= $end; $line++ ) {
            // 在行号前填充0
            $index_pad = str_pad($line + 1, strlen($end), 0, STR_PAD_LEFT );
            ($line + 1) == $focus && $codeHtml .= "<p style='height: ".$style['lineHeight']."px; width: 100%; _width: 95%; background-color: red; opacity: 0.4; filter:alpha(opacity=40); font-size:15px; font-weight: bold;'>";
            $codeHtml .= "<span style='margin-right: 10px;line-height: ".$style['lineHeight']."px; color: #807E7E;'>{$index_pad}</span>{$html[$line]}";
            $codeHtml .= ( ($line + 1) == $focus ? '</p>' : ($line != $end ? '<br />' : '') );
        }

        // 修正结束标签
        if ( substr( $codeHtml, -7 ) !== '</span>' ) {
            $codeHtml .= '</span>';
        }

        return <<<EOT
        <div style="position: relative; font-size: {$style['fontSize']}px; background-color: #BAD89A;">
            <div style="_width: 95%; line-height: {$style['lineHeight']}px; position: relative; z-index: 2; overflow: hidden; white-space:nowrap; text-overflow:ellipsis;">{$codeHtml}</div>
        </div>
EOT;
    }

    /**
     * 输出调试消息
     *
     * @return void
     */
    private static function showCmlPHPConsole()
    {
        if (Request::isAjax()) {
            if (Config::get('dump_use_php_console')) {
                self::$sqls && \Cml\dumpUsePHPConsole(self::$sqls, 'sql');
                \Cml\dumpUsePHPConsole(self::$tipInfo, 'tipInfo');
                \Cml\dumpUsePHPConsole(self::$includefile, 'includeFile');
            } else {
                $deBugLogData = array(
                    'tipInfo' => self::$tipInfo
                );
                self::$sqls && $deBugLogData['sql'] = self::$sqls;
                if (!empty($deBugLogData)) require CML_PATH.DIRECTORY_SEPARATOR.'Cml'.DIRECTORY_SEPARATOR.'ConsoleLog.php';
            }
        } else {
            echo '<div id="cmlphp_console_info" style="letter-spacing: -.0em;position: fixed;bottom:0;right:0;font-size:14px;width:100%;z-index: 999999;color: #000;text-align:left;">
                    <div id="cmlphp_console_info_switch" style="height: 28px; bottom: 0; color: rgb(0, 0, 0); cursor: pointer; display: block; width: 100%; border-top: 3px rgb(255, 102, 0) solid;">
                        <div style="background:#232323;color:#FFF;padding:2px 6px;height:28px;font-size:14px;">
                             <span id="cmlphp_console_info_simpleinfo">消耗时间<i>' . self::useTime() . 's</i> &nbsp;&nbsp; 消耗内存<i>' . self::useMemory() . ' </i></span>
                             <div style="float:right;margin:0 auto;width:110px;text-align:center;">
                                <svg id="cmlphp_console_info_logo" width="85" height="25" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
 <g>
  <rect fill="none" id="canvas_background" height="27" width="87" y="-1" x="-1"/>
  <g display="none" overflow="visible" y="0" x="0" height="100%" width="100%" id="canvasGrid">
   <rect fill="url(#gridpattern)" stroke-width="0" y="0" x="0" height="100%" width="100%"/>
  </g>
 </g>
 <g>
  <image xlink:href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAFUAAAAZCAYAAABAb2JNAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyFpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNS1jMDE0IDc5LjE1MTQ4MSwgMjAxMy8wMy8xMy0xMjowOToxNSAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENDIChXaW5kb3dzKSIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDowRjZFRTU5QUQ2MjAxMUU1QkFBREQ3NzMwM0IxOTZCRCIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDowRjZFRTU5QkQ2MjAxMUU1QkFBREQ3NzMwM0IxOTZCRCI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOjBGNkVFNTk4RDYyMDExRTVCQUFERDc3MzAzQjE5NkJEIiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjBGNkVFNTk5RDYyMDExRTVCQUFERDc3MzAzQjE5NkJEIi8+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+IZdrVgAAAzVJREFUeNrsmEtuFDEQhsvt7k4mbJgcYTgCOUGU7JEQWXEFFigSuQLMErGBNTv2KBKIEyRHGI5ALyHpdpv6bVfbE/LohozEwpbc73GVv/qr7ERZaym3+21FRpChZqgZam4Zaoaaoeb2r6288c3+8oSPr0kBPR+U9Gu/3iVLDfU9Eba92PvKeXpbsJ0zNyauC7VyT2H3Zh8a7ods85xtLrif8fXuYD91I/VJqR983PPyGmUn2jo9Pp8GdX85d0C/vVJ3Injylqi33lnDwjcMFnDxDF5NBesnM3fnQjof4Iku/HsKgY6QnjG493y95+57Ox98wmsJNp6p4JMfZx5tcD89HjNfb0uCMUGpc9JqHIStyjtt2NGi99HtMNn+75QqmQGA6JUGVHL+6GIdfoTKCqXHgz3AM30Mdtf7exX8lKaVH7sMdsa07crbmpz+xZUJ3Aq1lEksqDWrIX3we8CODelymzNH3D+5SYoqax671hFwqYP6gtL64RpAYzqCW2f8e/SLlqhNgu0EkACFMLbLcfOd1eu2RkOVSY2LnFcBuk8j5RQrk4+qWrlaRPT1mlEO+FtOX4YKuxJUjD2rcL1iJS0cZBtUKGksgJCOLr0D1MvOeqj87U/twUKxgN0ar0wECedZTbTD/cXHL3x/4OyI2iUbROEPd5owj6lKLcZD/dXS4LwNAOGouqJ8LDpE34dFIIWO5z0WGArpHmocJvtgC2M9cs+rMkJJJyrZIcHAdck3UlNlPi1LtU3KSqWtAwgb6Mujw82t/lOUetGlhT9OLAUrKU1paQhQhwUlAKhCuiuKCiJaHzP9Ldn1GitKV0lAbVAwUl13cSxXXkpvA1A3uqUqimZw8k6orZ+XOIoz6uysThaeBKrUR1lA1gBRrKE2lBb0PqQ4xpcyY5JAplslG3YdoljnT3hXSn3uIlCMKXY2CvXzy4aevntDzz9YF0UAQupcGtQqr07ATPepTqGqcWoQ52VrZd2uoBmUg94FkJ0RlTbD4lGXjXvm6h4H2Fj6c7+cAqWodl9qvC0XZBVXdm1iqai1tyOKrXRzX1BV/id1/jM1Q81Qc8tQM9QMNbcM9T9svwUYANvRWSdKGhh9AAAAAElFTkSuQmCC" id="svg_1" height="25" width="85" y="0" x="0"/>
 </g>
</svg><svg version="1.1" width=25 xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" height="24" viewBox="0 0 24 24" style="display:inline-block" enable-background="new 0 0 24 24" xml:space="preserve" id="cmlphp_console_info_minisize">
<path fill="#AAAAAA" d="M21.1,18.3c0.8,0.8,0.8,2,0,2.8c-0.4,0.4-0.9,0.6-1.4,0.6s-1-0.2-1.4-0.6L12,14.8l-6.3,6.3
    c-0.4,0.4-0.9,0.6-1.4,0.6s-1-0.2-1.4-0.6c-0.8-0.8-0.8-2,0-2.8L9.2,12L2.9,5.7c-0.8-0.8-0.8-2,0-2.8c0.8-0.8,2-0.8,2.8,0L12,9.2
    l6.3-6.3c0.8-0.8,2-0.8,2.8,0c0.8,0.8,0.8,2,0,2.8L14.8,12L21.1,18.3z"></path>
</svg>
                             </div>
                        </div>
                    </div>
                    <div id="cmlphp_console_info_content" style="display: none;background:white;margin:0;height: 390px;">
                        <div style="height:30px;padding: 6px 12px 0;border-bottom:1px solid #ececec;border-top:1px solid #ececec;font-size:16px">
                            <span>运行信息</span>
                        </div>
                        <div style="overflow:auto;height:352px;padding: 0; line-height: 24px">
                                <ul style="padding: 0; margin:0">
                                    ';
            if (count(self::$includefile) > 0) {
                echo '<li style="border-bottom:1px solid #EEE;font-size:14px;padding:0 12px;font-weight:bold;"><b>包含类库</b></li><li style="font-size:14px;padding:0 0 0 50px;">';
                foreach (self::$includefile as $file) {
                    echo "<span style='padding-left:10px;'>【{$file}】</span>";
                }
                echo '</li>';
            }
            if (count(self::$tipInfo) > 0) {
                echo '<li style="border-bottom:1px solid #EEE;font-size:14px;padding:0 12px;font-weight:bold;"><b>系统信息</b></li>';
                foreach (self::$tipInfo as $info) {
                    echo "<li style='font-size:14px;padding:0 0 0 60px;'>$info</li>";
                }
            }
            if (count(self::$sqls) > 0) {
                echo '<li style="border-bottom:1px solid #EEE;font-size:14px;padding:0 12px;font-weight:bold;"><b>SQL语句</b></li>';
                foreach (self::$sqls as $sql) {
                    echo "<li style='font-size:14px;padding:0 0 0 60px;'>$sql</li>";
                }
            }

            echo '</ul>
                        </div>
                    </div>
                </div>
                <script type="text/javascript">
                    (function(){
                        var show = false;
                        var switchShow  = document.getElementById(\'cmlphp_console_info_switch\');
                        var trace    = document.getElementById(\'cmlphp_console_info_content\');
                        var cmlphp_console_info_minisize = document.getElementById(\'cmlphp_console_info_minisize\');
                        var cmlphp_console_info = document.getElementById("cmlphp_console_info");
                        var cmlphp_console_info_simpleinfo = document.getElementById("cmlphp_console_info_simpleinfo");
                        var cmlphp_console_info_logo = document.getElementById("cmlphp_console_info_logo");

                        cmlphp_console_info_minisize.onclick = function() {
                            cmlphp_console_info_minisize.style.display = "none";
                            show = true;
                            trace.style.display = "none";
                            cmlphp_console_info.style.width = "110px";
                            cmlphp_console_info_simpleinfo.style.display="none"
                        };

                        cmlphp_console_info_logo.onclick = function() {
                            cmlphp_console_info_minisize.style.display = "inline-block";
                            cmlphp_console_info_simpleinfo.style.display = "inline-block";
                            cmlphp_console_info.style.width = "100%";
                        };

                        switchShow.onclick = function(){
                            trace.style.display = show ?  \'none\' : \'block\';                      
                            show = !show;
                        };
                    })();
                </script>';
        }
    }
}

/**
修改自dbug官方网站 http://dbug.ospinto.com/

使用方法：
include_once("dBug.php");
new dBug($myVariable1);
new dBug($myVariable2); //建议每次都创建一个新实例
new dBug($arr);

$test = new someClass('123');
new dBug($test);

$result = mysql_query('select * from tblname');
new dBug($result);

$xmlData = "./data.xml";
new dBug($xmlData, "xml");
 **/

/**
 * debug依赖的第三方库
 *
 * @package Cml
 */
class dBug
{
    private $xmlCData;
    private $xmlSData;
    private $xmlDData;
    private $xmlCount = 0;
    private $xmlAttrib;
    private $xmlName;
    private $arrType = array("array", "object", "resource", "boolean", "NULL");
    private $bInitialized = false;
    private $bCollapsed = false;
    private $arrHistory = array();

    /**
     * 构造方法
     *
     * @param mixed $var 要打印的变量
     * @param string $forceType
     * @param bool $bCollapsed
     */
    public function __construct($var, $forceType = "", $bCollapsed = false)
    {
        //include js and css scripts
        $this->initJSandCSS();
        $arrAccept=array("array", "object", "xml"); //array of variable types that can be "forced"
        $this->bCollapsed = $bCollapsed;
        if (in_array($forceType,$arrAccept)) {
            $this->{"varIs" . ucfirst($forceType)}($var);
        } else {
            $this->checkType($var);
        }
    }

    //get variable name
    private function getVariableName()
    {
        $arrBacktrace = debug_backtrace();

        //possible 'included' functions
        $arrInclude = array("include", "include_once", "require", "require_once");

        //check for any included/required files. if found, get array of the last included file (they contain the right line numbers)
        for ($i = count($arrBacktrace) - 1; $i >= 0; $i--) {
            $arrCurrent = $arrBacktrace[$i];
            if (
                array_key_exists("function", $arrCurrent)
                && (
                    in_array($arrCurrent["function"], $arrInclude) || (0 != strcasecmp($arrCurrent["function"], "dbug"))
                )
            ) {
                continue;
            }
            $arrFile = $arrCurrent;
            break;
        }

        if (isset($arrFile)) {
            $arrLines = file($arrFile["file"]);
            $code = $arrLines[($arrFile["line"]-1)];

            //find call to dBug class
            preg_match('/\bnew dBug\s*\(\s*(.+)\s*\);/i', $code, $arrMatches);

            return $arrMatches[1];
        }
        return "";
    }

    //create the main table header
    private function makeTableHeader($type, $header, $colspan = 2)
    {
        if (!$this->bInitialized) {
            $header = $this->getVariableName() . " (" . $header . ")";
            $this->bInitialized = true;
        }
        $str_i = ($this->bCollapsed) ? "style=\"font-style:italic\" " : "";

        echo "<table cellspacing=2 cellpadding=3 class=\"dBug_".$type."\">
                <tr>
                    <td ".$str_i."class=\"dBug_".$type."Header\" colspan=".$colspan." onClick='dBug_toggleTable(this)'>".$header."</td>
                </tr>";
    }

    //create the table row header
    private function makeTDHeader($type,$header)
    {
        $str_d = ($this->bCollapsed) ? " style=\"display:none\"" : "";
        echo "<tr".$str_d.">
                <td valign=\"top\" onClick='dBug_toggleRow(this)' class=\"dBug_".$type."Key\">".$header."</td>
                <td>";
    }

    //close table row
    private function closeTDRow()
    {
        return "</td></tr>\n";
    }

    //error
    private function  error($type)
    {
        $error = "Error: Variable cannot be a";
        // this just checks if the type starts with a vowel or "x" and displays either "a" or "an"
        if (
        in_array(substr($type, 0, 1), array("a", "e", "i", "o", "u", "x"))
        ) {
            $error .= "n";
        }
        return ($error." ".$type." type");
    }

    //check variable type
    private function checkType($var)
    {
        switch (gettype($var)) {
            case "resource":
                $this->varIsResource($var);
                break;
            case "object":
                $this->varIsObject($var);
                break;
            case "array":
                $this->varIsArray($var);
                break;
            case "NULL":
                $this->varIsNULL();
                break;
            case "boolean":
                $this->varIsBoolean($var);
                break;
            default:
                $var=($var=="") ? "[empty string]" : $var;
                echo "<table cellspacing=0><tr>\n<td>".$var."</td>\n</tr>\n</table>\n";
                break;
        }
    }

    //if variable is a NULL type
    private function varIsNULL()
    {
        echo "NULL";
    }

    //if variable is a boolean type
    private function varIsBoolean($var)
    {
        $var = ($var==1) ? "true" : "false";
        echo $var;
    }

    //if variable is an array type
    private function varIsArray($var)
    {
        $var_ser = serialize($var);
        array_push($this->arrHistory, $var_ser);

        $this->makeTableHeader("array", "array");
        if (is_array($var)) {
            foreach ($var as $key => $value) {
                $this->makeTDHeader("array", $key);

                //check for recursion
                if (is_array($value)) {
                    $var_ser = serialize($value);
                    if (in_array($var_ser, $this->arrHistory, true))
                        $value = "*RECURSION*";
                }

                if (in_array(gettype($value),$this->arrType)) {
                    $this->checkType($value);
                } else {
                    $value = (trim($value) == "") ? "[empty string]" : $value;
                    echo $value;
                }
                echo $this->closeTDRow();
            }
        } else {
            echo "<tr><td>" . $this->error("array") . $this->closeTDRow();
        }
        array_pop($this->arrHistory);
        echo "</table>";
    }

    //if variable is an object type
    private function varIsObject($var)
    {
        $var_ser = serialize($var);
        array_push($this->arrHistory, $var_ser);
        $this->makeTableHeader("object", "object");

        if (is_object($var)) {
            $arrObjVars = get_object_vars($var);
            foreach ($arrObjVars as $key => $value) {

                $value = (!is_object($value) && !is_array($value) && trim($value) == "") ? "[empty string]" : $value;
                $this->makeTDHeader("object",$key);

                //check for recursion
                if (is_object($value) || is_array($value)) {
                    $var_ser = serialize($value);
                    if (in_array($var_ser, $this->arrHistory, TRUE)) {
                        $value = (is_object($value)) ? "*RECURSION* -> $".get_class($value) : "*RECURSION*";

                    }
                }
                if (in_array(gettype($value), $this->arrType)) {
                    $this->checkType($value);
                } else {
                    echo $value;
                }
                echo $this->closeTDRow();
            }
            $arrObjMethods = get_class_methods(get_class($var));
            foreach ($arrObjMethods as $key => $value) {
                $this->makeTDHeader("object", $value);
                echo "[function]".$this->closeTDRow();
            }
        } else {
            echo "<tr><td>".$this->error("object").$this->closeTDRow();
        }
        array_pop($this->arrHistory);
        echo "</table>";
    }

    //if variable is a resource type
    private function varIsResource($var)
    {
        $this->makeTableHeader("resourceC", "resource", 1);
        echo "<tr>\n<td>\n";
        switch (get_resource_type($var)) {
            case "fbsql result":
            case "mssql result":
            case "msql query":
            case "pgsql result":
            case "sybase-db result":
            case "sybase-ct result":
            case "mysql result":
                $db=current(explode(" ", get_resource_type($var)));
                $this->varIsDBResource($var, $db);
                break;
            case "gd":
                $this->varIsGDResource($var);
                break;
            case "xml":
                $this->varIsXmlResource($var);
                break;
            default:
                echo get_resource_type($var) . $this->closeTDRow();
                break;
        }
        echo $this->closeTDRow()."</table>\n";
    }

    //if variable is a database resource type
    private function varIsDBResource($var, $db = "mysql")
    {
        if ($db == "pgsql") {
            $db = "pg";
        }
        if ($db == "sybase-db" || $db == "sybase-ct") {
            $db = "sybase";
        }
        $arrFields = array("name", "type", "flags");
        $numrows = call_user_func($db."_num_rows",$var);
        $numfields = call_user_func($db."_num_fields",$var);
        $this->makeTableHeader("resource", $db." result", $numfields + 1);
        echo "<tr><td class=\"dBug_resourceKey\">&nbsp;</td>";
        $field = array();
        for ($i=0;$i<$numfields;$i++) {
            $field_header = $field_name = "";
            for ($j = 0; $j < count($arrFields); $j++) {
                $db_func = $db."_field_".$arrFields[$j];
                if (function_exists($db_func)) {
                    $fheader = call_user_func($db_func, $var, $i). " ";
                    if ($j==0) {
                        $field_name = $fheader;
                    } else {
                        $field_header .= $fheader;
                    }
                }
            }
            $field[$i] = call_user_func($db."_fetch_field", $var, $i);
            echo "<td class=\"dBug_resourceKey\" title=\"".$field_header."\">".$field_name."</td>";
        }
        echo "</tr>";
        for ($i = 0; $i < $numrows; $i++) {
            $row=call_user_func($db."_fetch_array", $var, constant(strtoupper($db)."_ASSOC"));
            echo "<tr>\n";
            echo "<td class=\"dBug_resourceKey\">".($i+1)."</td>";
            for ($k = 0; $k < $numfields; $k++) {
                $fieldrow = $row[($field[$k]->name)];
                $fieldrow = ($fieldrow == "") ? "[empty string]" : $fieldrow;
                echo "<td>".$fieldrow."</td>\n";
            }
            echo "</tr>\n";
        }
        echo "</table>";
        if ($numrows > 0) {
            call_user_func($db."_data_seek", $var, 0);
        }
    }

    //if variable is an image/gd resource type
    private function varIsGDResource($var)
    {
        $this->makeTableHeader("resource", "gd", 2);
        $this->makeTDHeader("resource", "Width");
        echo imagesx($var).$this->closeTDRow();
        $this->makeTDHeader("resource", "Height");
        echo imagesy($var).$this->closeTDRow();
        $this->makeTDHeader("resource", "Colors");
        echo imagecolorstotal($var).$this->closeTDRow();
        echo "</table>";
    }

    //if variable is an xml type
    private function varIsXml($var)
    {
        $this->varIsXmlResource($var);
    }

    //if variable is an xml resource type
    private function varIsXmlResource($var)
    {
        $xml_parser = xml_parser_create();
        xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING,0);
        xml_set_element_handler($xml_parser, array(&$this, "xmlStartElement"), array(&$this, "xmlEndElement"));
        xml_set_character_data_handler($xml_parser, array(&$this, "xmlCharacterData"));
        xml_set_default_handler($xml_parser, array(&$this, "xmlDefaultHandler"));

        $this->makeTableHeader("xml", "xml document",2);
        $this->makeTDHeader("xml", "xmlRoot");

        //attempt to open xml file
        $bFile = (!($fp=@fopen($var, "r"))) ? false : true;

        //read xml file
        if ($bFile) {
            while ($data = str_replace("\n", "", fread($fp, 4096))) {
                $this->xmlParse($xml_parser, $data, feof($fp));
            }
        }
        //if xml is not a file, attempt to read it as a string
        else {
            if (!is_string($var)) {
                echo $this->error("xml").$this->closeTDRow()."</table>\n";
                return;
            }
            $data = $var;
            $this->xmlParse($xml_parser,$data,1);
        }

        echo $this->closeTDRow()."</table>\n";

    }

    //parse xml
    private function xmlParse($xml_parser, $data, $bFinal)
    {
        if (!xml_parse($xml_parser,$data,$bFinal)) {
            die(sprintf("XML error: %s at line %d\n",
                xml_error_string(xml_get_error_code($xml_parser)),
                xml_get_current_line_number($xml_parser)));
        }
    }

    //xml: inititiated when a start tag is encountered
    private function xmlStartElement($parser, $name, $attribs)
    {
        $this->xmlAttrib[$this->xmlCount] = $attribs;
        $this->xmlName[$this->xmlCount] = $name;
        $this->xmlSData[$this->xmlCount] = '$this->makeTableHeader("xml","xml element",2);';
        $this->xmlSData[$this->xmlCount] .= '$this->makeTDHeader("xml","xmlName");';
        $this->xmlSData[$this->xmlCount] .= 'echo "<strong>'.$this->xmlName[$this->xmlCount].'</strong>".$this->closeTDRow();';
        $this->xmlSData[$this->xmlCount] .= '$this->makeTDHeader("xml","xmlAttributes");';
        if (count($attribs)>0) {
            $this->xmlSData[$this->xmlCount] .= '$this->varIsArray($this->xmlAttrib[' . $this->xmlCount . ']);';
        } else {
            $this->xmlSData[$this->xmlCount] .= 'echo "&nbsp;";';
        }
        $this->xmlSData[$this->xmlCount].='echo $this->closeTDRow();';
        $this->xmlCount++;
    }

    //xml: initiated when an end tag is encountered
    private function xmlEndElement($parser, $name)
    {
        for ($i = 0; $i < $this->xmlCount; $i++) {
            eval($this->xmlSData[$i]);
            $this->makeTDHeader("xml", "xmlText");
            echo (!empty($this->xmlCData[$i])) ? $this->xmlCData[$i] : "&nbsp;";
            echo $this->closeTDRow();
            $this->makeTDHeader("xml","xmlComment");
            echo (!empty($this->xmlDData[$i])) ? $this->xmlDData[$i] : "&nbsp;";
            echo $this->closeTDRow();
            $this->makeTDHeader("xml","xmlChildren");
            unset($this->xmlCData[$i],$this->xmlDData[$i]);
        }
        echo $this->closeTDRow();
        echo "</table>";
        $this->xmlCount=0;
    }

    //xml: initiated when text between tags is encountered
    private function xmlCharacterData($parser,$data)
    {
        $count=$this->xmlCount-1;
        if (!empty($this->xmlCData[$count])) {
            $this->xmlCData[$count] .= $data;
        } else {
            $this->xmlCData[$count] = $data;
        }
    }

    //xml: initiated when a comment or other miscellaneous texts is encountered
    private function xmlDefaultHandler($parser, $data)
    {
        //strip '<!--' and '-->' off comments
        $data = str_replace(array("&lt;!--","--&gt;"), "", htmlspecialchars($data));
        $count = $this->xmlCount - 1;
        if (!empty($this->xmlDData[$count])) {
            $this->xmlDData[$count] .= $data;
        } else {
            $this->xmlDData[$count] = $data;
        }
    }

    private function initJSandCSS()
    {
        echo <<<SCRIPTS
            <script language="JavaScript">
            /* code modified from ColdFusion's cfdump code */
                function dBug_toggleRow(source) {
                    var target = (document.all) ? source.parentElement.cells[1] : source.parentNode.lastChild;
                    dBug_toggleTarget(target,dBug_toggleSource(source));
                }

                function dBug_toggleSource(source) {
                    if (source.style.fontStyle=='italic') {
                        source.style.fontStyle='normal';
                        source.title='click to collapse';
                        return 'open';
                    } else {
                        source.style.fontStyle='italic';
                        source.title='click to expand';
                        return 'closed';
                    }
                }

                function dBug_toggleTarget(target,switchToState) {
                    target.style.display = (switchToState=='open') ? '' : 'none';
                }

                function dBug_toggleTable(source) {
                    var switchToState=dBug_toggleSource(source);
                    if (document.all) {
                        var table=source.parentElement.parentElement;
                        for (var i=1;i<table.rows.length;i++) {
                            target=table.rows[i];
                            dBug_toggleTarget(target,switchToState);
                        }
                    }
                    else {
                        var table=source.parentNode.parentNode;
                        for (var i=1;i<table.childNodes.length;i++) {
                            target=table.childNodes[i];
                            if (target.style) {
                                dBug_toggleTarget(target,switchToState);
                            }
                        }
                    }
                }
            </script>

            <style type="text/css">
                table.dBug_array,table.dBug_object,table.dBug_resource,table.dBug_resourceC,table.dBug_xml {
                    font-family:Verdana, Arial, Helvetica, sans-serif; color:#000000; font-size:12px;
                }

                .dBug_arrayHeader,
                .dBug_objectHeader,
                .dBug_resourceHeader,
                .dBug_resourceCHeader,
                .dBug_xmlHeader
                    { font-weight:bold; color:#FFFFFF; cursor:pointer; }

                .dBug_arrayKey,
                .dBug_objectKey,
                .dBug_xmlKey
                    { cursor:pointer; }

                /* array */
                table.dBug_array { background-color:#006600; }
                table.dBug_array td { background-color:#FFFFFF; }
                table.dBug_array td.dBug_arrayHeader { background-color:#009900; }
                table.dBug_array td.dBug_arrayKey { background-color:#CCFFCC; }

                /* object */
                table.dBug_object { background-color:#0000CC; }
                table.dBug_object td { background-color:#FFFFFF; }
                table.dBug_object td.dBug_objectHeader { background-color:#4444CC; }
                table.dBug_object td.dBug_objectKey { background-color:#CCDDFF; }

                /* resource */
                table.dBug_resourceC { background-color:#884488; }
                table.dBug_resourceC td { background-color:#FFFFFF; }
                table.dBug_resourceC td.dBug_resourceCHeader { background-color:#AA66AA; }
                table.dBug_resourceC td.dBug_resourceCKey { background-color:#FFDDFF; }

                /* resource */
                table.dBug_resource { background-color:#884488; }
                table.dBug_resource td { background-color:#FFFFFF; }
                table.dBug_resource td.dBug_resourceHeader { background-color:#AA66AA; }
                table.dBug_resource td.dBug_resourceKey { background-color:#FFDDFF; }

                /* xml */
                table.dBug_xml { background-color:#888888; }
                table.dBug_xml td { background-color:#FFFFFF; }
                table.dBug_xml td.dBug_xmlHeader { background-color:#AAAAAA; }
                table.dBug_xml td.dBug_xmlKey { background-color:#DDDDDD; }
            </style>
SCRIPTS;
    }
}