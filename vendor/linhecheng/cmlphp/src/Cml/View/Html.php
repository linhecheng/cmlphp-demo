<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.51beautylife.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-8 下午3:07
 * @version  2.5
 * cml框架 视图 html渲染引擎
 * *********************************************************** */

namespace Cml\View;

use Cml\Cml;
use Cml\Config;
use Cml\Lang;
use Cml\Route;
use Cml\Secure;

/**
 * 视图 html渲染引擎
 *
 * @package Cml\View
 */
class Html extends Base
{
    /**
     * 模板参数信息
     *
     * @var array
     */
    private $options = array();

    /**
     * 子模板block内容数组
     *
     * @var array
     */
    private $layoutBlockData = array();

    /**
     * 模板布局文件
     *
     * @var null
     */
    private $layout = null;

    /**
     * 构造方法
     *
     */
    public function __construct()
    {
        $this->options = array(
            'templateDir' => 'templates' . DIRECTORY_SEPARATOR, //模板文件所在目录
            'cacheDir' => 'templates' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR, //缓存文件存放目录
            'autoUpdate' => true, //当模板文件改动时是否重新生成缓存
            'leftDeper' => preg_quote(Config::get('html_left_deper')),
            'rightDeper' => preg_quote(Config::get('html_right_deper'))
        );
    }

    /**
     * 设定模板参数
     *
     * @param  string | array $name  参数名称
     * @param  mixed  $value 参数值
     *
     * @return void
     */
    public function set($name, $value = '')
    {
        if (is_array($name)) {
            $this->options = array_merge($this->options, $name);
        } else {
            $this->options[$name] = $value;
        }
    }

    /**
     * 通过魔术方法设定模板参数
     *
     * @param  string $name  参数名称
     * @param  mixed  $value 参数值
     *
     * @return void
     */
    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    /**
     * 获取模板文件缓存
     *
     * @param  string $file 模板文件名称
     * @param int $type 缓存类型0当前操作的模板的缓存 1包含的模板的缓存
     *
     * @return string
     */
    public function getFile($file, $type = 0)
    {
        $type == 1 && $file = $this->initBaseDir($file);//初始化路径
        //$file = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $file);
        $cacheFile = $this->getCacheFile($file);
        if ($this->options['autoUpdate']) {
            $tplFile = $this->getTplFile($file);
            is_readable($tplFile) || \Cml\throwException(Lang::get('_TEMPLATE_FILE_NOT_FOUND_', $tplFile));
            if (!is_file($cacheFile)) {
                if ($type !==1 && !is_null($this->layout)) {
                    is_readable($this->layout) || \Cml\throwException(Lang::get('_TEMPLATE_FILE_NOT_FOUND_', $this->layout));
                }
                $this->compile($tplFile, $cacheFile, $type);
                return $cacheFile;
            }

            $compile = false;
            $tplMtime = filemtime($tplFile);
            $cacheMtime = filemtime($cacheFile);
            if ($cacheMtime && $tplMtime) {
                ($cacheMtime < $tplMtime) && $compile = true;
            } else {//获取mtime失败
                $compile = true;
            }

            if ($compile && $type !==1 && !is_null($this->layout)) {
                is_readable($this->layout) || \Cml\throwException(Lang::get('_TEMPLATE_FILE_NOT_FOUND_', $this->layout));
            }

            //当子模板未修改时判断布局模板是否修改
            if (!$compile && $type !==1 && !is_null($this->layout)) {
                is_readable($this->layout) || \Cml\throwException(Lang::get('_TEMPLATE_FILE_NOT_FOUND_', $this->layout));
                $layoutMTime = filemtime($this->layout);
                if ($layoutMTime) {
                    $cacheMtime < $layoutMTime && $compile = true;
                } else {
                    $compile = true;
                }
            }

            $compile && $this->compile($tplFile, $cacheFile, $type);
        }
        return $cacheFile;
    }

    /**
     * 对模板文件进行缓存
     *
     * @param  string  $tplFile    模板文件名
     * @param  string $cacheFile 模板缓存文件名
     * @param int $type 缓存类型0当前操作的模板的缓存 1包含的模板的缓存
     *
     * @return void
     */
    private function compile($tplFile, $cacheFile, $type)
    {
        $leftDeper = $this->options['leftDeper'];
        $rightDeper = $this->options['rightDeper'];

        //取得模板内容
        //$template = file_get_contents($tplFile);
        $template = $this->getTplContent($tplFile, $type);

        //要替换的标签
        $exp = array(
            '#\<\?(=|php)(.+?)\?\>#s', //替换php标签
            "#$leftDeper(\\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*?\\[\S+?\\]\\[\S+?\\]|\\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*?\\[\S+?\\]|\\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*?);?$rightDeper#", //替换变量 $a['name']这种一维数组以及$a['name']['name']这种二维数组
            "#$leftDeper(\\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*?)\\.([a-zA-Z0-9_\x7f-\xff]+);?$rightDeper#", //替换$a.key这种一维数组
            "#$leftDeper(\\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*?)\\.([a-zA-Z0-9_\x7f-\xff]+)\\.([a-zA-Z0-9_\x7f-\xff]+);?$rightDeper#", //替换$a.key.key这种二维数组
            '#'.$leftDeper.'template\s+([a-z0-9A-Z_\.\/]+);?'.$rightDeper.'[\n\r\t]*#',//替换模板载入命令
            '#'.$leftDeper.'eval\s+(.+?)'.$rightDeper.'#s',//替换eval
            '#'.$leftDeper.'echo\s+(.+?)'.$rightDeper.'#s', //替换 echo
            '#'.$leftDeper.'if\s+(.+?)'.$rightDeper.'#s',//替换if
            '#'.$leftDeper.'(elseif|elseif)\s+(.+?)'.$rightDeper.'#s', //替换 elseif
            '#'.$leftDeper.'else'.$rightDeper.'#', //替换 else
            '#'.$leftDeper.'\/if'.$rightDeper.'#',//替换 /if
            '#'.$leftDeper.'(loop|foreach)\s+(\S+)\s+(\S+)'.$rightDeper.'#s',//替换loop|foreach
            '#'.$leftDeper.'(loop|foreach)\s+(\S+)\s+(\S+)\s+(\S+)'.$rightDeper.'#s',//替换loop|foreach
            '#'.$leftDeper.'\/(loop|foreach)'.$rightDeper.'#',//替换 /foreach|/loop
            '#'.$leftDeper.'hook\s+(\w+?)\s*'.$rightDeper.'#i',//替换 hook
            '#'.$leftDeper.'(get|post|request)\s+(\w+?)\s*'.$rightDeper.'#i',//替换 get/post/request
            '#'.$leftDeper.'lang\s+([A-Za-z0-9_\.]+)\s*'.$rightDeper.'#i',//替换 lang
            '#'.$leftDeper.'config\s+([A-Za-z0-9_\.]+)\s*'.$rightDeper.'#i',//替换 config
            '#'.$leftDeper.'url\s+(.*?)\s*'.$rightDeper.'#i',//替换 url
            '#'.$leftDeper.'public'.$rightDeper.'#i',//替换 {{public}}
            '#'.$leftDeper.'self'.$rightDeper.'#i',//替换 {{self}}
            '#'.$leftDeper.'token'.$rightDeper.'#i',//替换 {{token}}
            '#'.$leftDeper.'controller'.$rightDeper.'#i',//替换 {{controller}}
            '#'.$leftDeper.'action'.$rightDeper.'#i',//替换 {{action}}
            '#'.$leftDeper.'urldeper'.$rightDeper.'#i',//替换 {{urldeper}}
            '#'.$leftDeper.' \\?\\>[\n\r]*\\<\\?'.$rightDeper.'#', //删除 PHP 代码断间多余的空格及换行
            '#(href\s*?=\s*?"\s*?"|href\s*?=\s*?\'\s*?\')#',
            '#(src\s*?=\s*?"\s*?"|src\s*?=\s*?\'\s*?\')#',
            '#'.$leftDeper.'assert\s+(.+?)\s*'.$rightDeper.'#i',//替换 assert
            '#'.$leftDeper.'comment\s+(.+?)\s*'.$rightDeper.'#i',//替换 comment 模板注释
        );

        //替换后的内容
        $replace = array(
            '&lt;?${1}${2}?&gt',
            '<?php echo ${1};?>',
            '<?php echo ${1}[\'${2}\'];?>',
            '<?php echo ${1}[\'${2}\'][\'${3}\'];?>',
            '<?php require(\Cml\View::getEngine()->getFile(\'${1}\', 1)); ?>',
            '<?php ${1};?>',
            '<?php echo ${1};?>',
            '<?php if (${1}) { ?>',
            '<?php } elseif (${2}) { ?>',
            '<?php } else { ?>',
            '<?php } ?>',
            '<?php if (is_array(${2})) { foreach (${2} as ${3}) { ?>',
            '<?php if (is_array(${2})) { foreach (${2} as ${3} => ${4}) { ?>',
            '<?php } } ?>',
            '<?php \Cml\Plugin::hook("${1}");?>',
            '<?php echo \Cml\Http\Input::${1}String("${2}");?>',
            '<?php echo \Cml\Lang::get("${1}");?>',
            '<?php echo \Cml\Config::get("${1}");?>',
            '<?php \Cml\Http\Response::url(${1});?>',

            '<?php echo \Cml\Config::get("static__path", \Cml\Route::$urlParams["root"]."public/");?>',//替换 {{public}}
            '<?php echo strip_tags($_SERVER["REQUEST_URI"]); ?>',//替换 {{self}}
            '<input type="hidden" name="CML_TOKEN" value="<?php echo \Cml\Secure::getToken();?>" />',//替换 {{token}}
            '<?php echo \Cml\Route::$urlParams["controller"]; ?>',//替换 {{controller}}
            '<?php echo \Cml\Route::$urlParams["action"]; ?>',//替换 {{action}}
            '<?php echo \Cml\Config::get("url_model") == 3 ? "&" : "?"; ?>',//替换 {{urldeper}}
            '',
            'href="javascript:void(0);"',
            'src="javascript:void(0);"',
            '<?php echo \Cml\Tools\StaticResource::parseResourceUrl("${1}");?>',//静态资源
            '',
        );
        //执行替换
        $template = preg_replace($exp, $replace, $template);

        if (!Cml::$debug) {
            /* 去除html空格与换行 */
            $find = array('~>\s+<~','~>(\s+\n|\r)~');
            $replace = array('><','>');
            $template = preg_replace($find, $replace, $template);
            $template = str_replace('?><?php','',$template);
        }

        //添加 头信息

        $template = '<?php if (!class_exists(\'\Cml\View\')) die(\'Access Denied\');?>' . $template;

        //写入缓存文件
        $this->makePath($cacheFile);
        file_put_contents($cacheFile, $template, LOCK_EX);
    }

    /**
     * 获取模板文件内容  使用布局的时候返回处理完的模板
     *
     * @param $tplFile
     * @param int $type 缓存类型0当前操作的模板的缓存 1包含的模板的缓存
     *
     * @return string
     */
    private function getTplContent($tplFile, $type)
    {
        if ($type === 0 && !is_null($this->layout)) {//主模板且存在模板布局
            $layoutCon = file_get_contents($this->layout);
            $tplCon = file_get_contents($tplFile);
            $ldeper = $this->options['leftDeper'];
            $rdeper = $this->options['rightDeper'];

            //获取子模板内容
            $presult = preg_match_all(
                '#'.$ldeper.'to\s+([a_zA-Z]+?)'.$rdeper.'(.*?)'.$ldeper.'\/to'.$rdeper.'#is',
                $tplCon,
                $tmpl
            );
            $tplCon = null;
            if ($presult > 0) {
                array_shift($tmpl);
            }
            //保存子模板提取完的区块内容
            for ($i = 0; $i < $presult; $i++) {
                $this->layoutBlockData[$tmpl[0][$i]] = $tmpl[1][$i];
            }
            $presult = null;

            //将子模板内容替换到布局文件返回
            $layoutBlockData = &$this->layoutBlockData;
            $layoutCon = preg_replace_callback(
                '#'.$ldeper.'block\s+([a_zA-Z]+?)'.$rdeper.'(.*?)'.$ldeper.'\/block'.$rdeper.'#is',
                function($matches) use($layoutBlockData) {
                    array_shift($matches);
                    if (isset($layoutBlockData[$matches[0]])) {
                        //替换{parent}标签并返回
                        return str_replace(
                            Config::get('html_left_deper').'parent'.Config::get('html_right_deper'),
                            $matches[1],
                            $layoutBlockData[$matches[0]]
                        );
                    } else {
                        return '';
                    }
                },
                $layoutCon
            );
            unset($layoutBlockData);
            $this->layoutBlockData = array();
            return $layoutCon;//返回替换完的布局文件内容
        } else {
            return file_get_contents($tplFile);
        }
    }

    /**
     * 获取模板文件名及路径
     *
     * @param  string $file 模板文件名称
     *
     * @return string
     */
    private function getTplFile($file)
    {
        return $this->options['templateDir'] . $file . Config::get('html_template_suffix');
    }

    /**
     * 获取模板缓存文件名及路径
     *
     * @param  string $file 模板文件名称
     *
     * @return string
     */
    private function getCacheFile($file)
    {
        return $this->options['cacheDir'].$file.'.cache.php';
    }

    /**
     * 根据指定的路径创建不存在的文件夹
     *
     * @param  string  $path 路径/文件夹名称
     *
     * @return string
     */
    private function makePath($path)
    {
        $path = dirname($path);
        if (!is_dir($path) && !mkdir($path, 0700, true)) \Cml\throwException(Lang::get('_CREATE_DIR_ERROR_')."[{$path}]");
        return true;
    }

    /**
     * 初始化目录
     *
     * @param string $templateFile 模板文件名
     * @param bool|false $inOtherApp 是否在其它app
     *
     * @return string
     */
    private function initBaseDir($templateFile, $inOtherApp = false) {
        $baseDir = CML_IS_MULTI_MODULES
            ? Config::get('application_dir') . (
            $inOtherApp
                ? DIRECTORY_SEPARATOR.$inOtherApp.DIRECTORY_SEPARATOR
                : Route::$urlParams['path']
            )
            : '';
        $baseDir .=  'View' . (Config::get('html_theme') != '' ? DIRECTORY_SEPARATOR . Config::get('html_theme') : '');

        if ($templateFile === '' ) {
            $baseDir .= (CML_IS_MULTI_MODULES ? DIRECTORY_SEPARATOR : Route::$urlParams['path']) .
                Route::$urlParams['controller'].DIRECTORY_SEPARATOR;
            $file = Route::$urlParams['action'];
        } else {
            $baseDir .= DIRECTORY_SEPARATOR . dirname($templateFile).DIRECTORY_SEPARATOR;
            $file = basename($templateFile);
        }

        $options = array(
            'templateDir' => CML_APP_FULL_PATH . DIRECTORY_SEPARATOR . $baseDir, //指定模板文件存放目录
            'cacheDir' => CML_RUNTIME_CACHE_PATH.DIRECTORY_SEPARATOR. $baseDir, //指定缓存文件存放目录
            'autoUpdate' => true, //当模板修改时自动更新缓存
        );

        $this->set($options);
        return $file;
    }

    /**
     * 模板显示 调用内置的模板引擎显示方法，
     *
     * @param string $templateFile 指定要调用的模板文件 默认为空 由系统自动定位模板文件
     * @param bool $inOtherApp 是否为载入其它应用的模板
     *
     * @return void
     */
    public function display($templateFile = '', $inOtherApp = false)
    {
        // 网页字符编码
        header('Content-Type:text/html; charset='.Config::get('default_charset'));

        echo $this->fetch($templateFile, $inOtherApp);

        Cml::cmlStop();
    }

    /**
     * 渲染模板获取内容 调用内置的模板引擎显示方法，
     *
     * @param string $templateFile 指定要调用的模板文件 默认为空 由系统自动定位模板文件
     * @param bool $inOtherApp 是否为载入其它应用的模板
     *
     * @return string
     */
    public function fetch($templateFile = '', $inOtherApp = false)
    {
        if (Config::get('form_token')) {
            Secure::setToken();
        }

        if (!empty($this->args)) {
            extract($this->args, EXTR_PREFIX_SAME, "xxx");
            $this->args = array();
        }

        ob_start();
        require $this->getFile($this->initBaseDir($templateFile, $inOtherApp));
        return ob_get_clean();
    }

    /**
     * 使用布局模板并渲染
     *
     * @param string $templateFile 模板文件
     * @param string $layout 布局文件
     * @param bool|false $layoutInOtherApp 面部是否在其它应用
     * @param bool|false $tplInOtherApp 模板是否在其它应用
     */
    public function displayWithLayout($templateFile ='', $layout = 'master', $layoutInOtherApp = false, $tplInOtherApp = false)
    {
        $this->layout = CML_APP_FULL_PATH.DIRECTORY_SEPARATOR .
            (
            CML_IS_MULTI_MODULES
                ? Config::get('application_dir') .
                (
                $layoutInOtherApp
                    ? DIRECTORY_SEPARATOR.$layoutInOtherApp.DIRECTORY_SEPARATOR
                    : Route::$urlParams['path']
                )
                : ''
            )
            . 'View' . DIRECTORY_SEPARATOR .
            (
            Config::get('html_theme') != '' ? Config::get('html_theme').DIRECTORY_SEPARATOR : ''
            )
            .'layout'.DIRECTORY_SEPARATOR.$layout.Config::get('html_template_suffix');
        $this->display($templateFile, $tplInOtherApp);
    }
}