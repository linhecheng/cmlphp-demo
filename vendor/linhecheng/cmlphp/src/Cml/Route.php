<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.51beautylife.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-8 下午3:07
 * @version  2.5
 * cml框架 URL解析类
 * *********************************************************** */
namespace Cml;

use Cml\Http\Request;

/**
 * Url解析类,负责路由及Url的解析
 *
 * @package Cml
 */
class Route
{
    /**
     * pathinfo数据用来提供给插件做一些其它事情
     *
     * @var array
     */
    private static $pathinfo = array();

    /**
     * 定义路由类型常量
     */
    const REQUEST_METHOD_GET = 1;
    const REQUEST_METHOD_POST = 2;
    const REQUEST_METHOD_PUT = 3;
    const REQUEST_METHOD_PATCH = 4;
    const REQUEST_METHOD_DELETE = 5;
    const REQUEST_METHOD_OPTIONS = 6;
    const REQUEST_METHOD_ANY = 7;
    const RESTROUTE = 8;

    /**
     * 路由规则 [请求方法对应的数字常量]pattern => [/models]/controller/action
     * 'blog/:aid\d' =>'Site/Index/read',
     * 'category/:cid\d/:p\d' =>'Index/index',
     * 'search/:keywords/:p'=>'Index/index',
     * 当路由为RESTROUTE路由时访问的时候会访问路由定义的方法名前加上访问方法如：
     * 定义了一条rest路由 'blog/:aid\d' =>'Site/Index/read' 当请求方法为GET时访问的方法为 Site模块Index控制器下的getRead方法当
     * 请求方法为POST时访问的方法为 Site模块Inde控制器下的postRead方法以此类推.
     *
     * @var array
     */
    private static $rules = array();

    /**
     * 解析得到的请求信息 含应用名、控制器、操作
     *
     * @var array
     */
    public static $urlParams = array(
        'path' => '',
        'controller' => '',
        'action' => '',
        'root' => '',
    );

    /**
     * 解析url
     *
     * @return void
     */
    public static function parseUrl()
    {
        $path = DIRECTORY_SEPARATOR;
        $urlModel = Config::get('url_model');
        $pathinfo = array();
        $isCli = Request::isCli(); //是否为命令行访问
        if ($isCli) {
            isset($_SERVER['argv'][1]) && $pathinfo = explode('/', $_SERVER['argv'][1]);
        } else {
            if ($urlModel === 1 || $urlModel === 2) { //pathinfo模式(含显示、隐藏index.php两种)SCRIPT_NAME
                if (isset($_GET[Config::get('var_pathinfo')])) {
                    $param = $_GET[Config::get('var_pathinfo')];
                } else {
                    $param = preg_replace('/(.*)\/(.*)\.php(.*)/i', '\\1\\3', $_SERVER['REQUEST_URI']);
                    $scriptName =  preg_replace('/(.*)\/(.*)\.php(.*)/i', '\\1', $_SERVER['SCRIPT_NAME']);

                    if (!empty($scriptName)) {
                        $param = substr($param, strpos($param, $scriptName) + strlen($scriptName));
                    }
                }
                $param = ltrim($param, '/');

                if (!empty($param)) { //无参数时直接跳过取默认操作
                    //获取参数
                    $pathinfo = explode(Config::get('url_pathinfo_depr'), trim(preg_replace(
                        array(
                            '/\\'.Config::get('url_html_suffix').'/',
                            '/\&.*/', '/\?.*/'
                        ),
                        '',
                        $param
                    ), Config::get('url_pathinfo_depr')));
                }
            } elseif ($urlModel === 3 && isset($_GET[Config::get('var_pathinfo')])) {//兼容模式
                $urlString = $_GET[Config::get('var_pathinfo')];
                unset($_GET[Config::get('var_pathinfo')]);
                $pathinfo = explode(Config::get('url_pathinfo_depr'), trim(str_replace(
                    Config::get('url_html_suffix'),
                    '',
                    ltrim($urlString, '/')
                ), Config::get('url_pathinfo_depr')));
            }
        }

        isset($pathinfo[0]) && empty($pathinfo[0]) && $pathinfo = array();

        //参数不完整获取默认配置
        if (empty($pathinfo)) {
            $pathinfo = explode('/', trim(Config::get('url_default_action'), '/'));
        }
        self::$pathinfo = $pathinfo;

        //检测路由
        if (self::$rules) {//配置了路由，所有请求通过路由处理
            $isRoute = self::isRoute($pathinfo);
            if ($isRoute[0]) {//匹配路由成功
                $routeArr = explode('/', $isRoute['route']);
                $isRoute = null;
                self::$urlParams['action']= array_pop($routeArr);
                self::$urlParams['controller'] = ucfirst(array_pop($routeArr));
                $controllerPath = '';
                while ($dir = array_shift($routeArr)) {
                    if (!CML_IS_MULTI_MODULES || $path == DIRECTORY_SEPARATOR) {
                        $path .= $dir.DIRECTORY_SEPARATOR;
                    } else {
                        $controllerPath .= $dir . DIRECTORY_SEPARATOR;
                    }
                }
                self::$urlParams['controller'] = $controllerPath . self::$urlParams['controller'];
                unset($routeArr);
            } else {
                self::findAction($pathinfo, $path); //未匹配到路由 按文件名映射查找
            }
        } else {
            self::findAction($pathinfo, $path);//未匹配到路由 按文件名映射查找
        }

        for ($i = 0; $i < count($pathinfo); $i += 2) {
            $_GET[$pathinfo[$i]] = $pathinfo[$i + 1];
        }

        unset($pathinfo);

        if (self::$urlParams['controller'] == '') {
            //控制器没取到,这时程序会 中止/404，取$path最后1位当做控制器用于异常提醒
            $dir  = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
            self::$urlParams['controller'] = ucfirst(array_pop($dir));
            $path = empty($dir) ? '' : DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $dir).DIRECTORY_SEPARATOR;
        }
        self::$urlParams['path'] = $path ? $path : DIRECTORY_SEPARATOR;
        unset($path);

        //定义URL常量
        $spath = dirname($_SERVER['SCRIPT_NAME']);
        if ($spath == '/' || $spath == '\\') {$spath = '';}
        //定义项目根目录地址
        self::$urlParams['root'] = $spath.'/';
        $_REQUEST = array_merge($_REQUEST, $_GET);
    }

    /**
     * 从文件查找控制器
     *
     * @param $pathinfo
     * @param $path
     */
    private static function findAction(&$pathinfo, &$path)
    {
        $controllerPath = $controllerName ='';
        while ($dir = array_shift($pathinfo)) {
            $controllerName = ucfirst($dir);
            if (
                CML_IS_MULTI_MODULES && $path != DIRECTORY_SEPARATOR &&
                is_file(CML_APP_MODULES_PATH . $path .'Controller' . DIRECTORY_SEPARATOR .$controllerPath. $controllerName . 'Controller.php')
            ) {
                self::$urlParams['controller'] = $controllerPath . $controllerName;
                break;
            } else if (!CML_IS_MULTI_MODULES && is_file(CML_APP_FULL_PATH.DIRECTORY_SEPARATOR.'Controller'.$path.$controllerName.'Controller.php')) {
                self::$urlParams['controller'] = $controllerName;
                break;
            } else {
                if (!CML_IS_MULTI_MODULES || $path == DIRECTORY_SEPARATOR) {
                    $path .= $dir.DIRECTORY_SEPARATOR;
                } else {
                    $controllerPath .= $dir . DIRECTORY_SEPARATOR;
                }
            }
        }
        empty(self::$urlParams['controller']) && self::$urlParams['controller'] = $controllerName;//用于404的时候挂载插件用
        self::$urlParams['action'] = array_shift($pathinfo);
    }

    /**
     * 增加get访问方式路由
     *
     * @param string $pattern 路由规则
     * @param string $action 执行的操作
     *
     * @return void
     */
    public static function get($pattern, $action)
    {
        self::$rules[self::REQUEST_METHOD_GET.$pattern] = $action;
    }

    /**
     * 增加post访问方式路由
     *
     * @param string $pattern 路由规则
     * @param string $action 执行的操作
     *
     * @return void
     */
    public static function post($pattern, $action)
    {
        self::$rules[self::REQUEST_METHOD_POST.$pattern] = $action;
    }

    /**
     * 增加put访问方式路由
     *
     * @param string $pattern 路由规则
     * @param string $action 执行的操作
     *
     * @return void
     */
    public static function put($pattern, $action)
    {
        self::$rules[self::REQUEST_METHOD_PUT.$pattern] = $action;
    }

    /**
     * 增加patch访问方式路由
     *
     * @param string $pattern 路由规则
     * @param string $action 执行的操作
     *
     * @return void
     */
    public static function patch($pattern, $action)
    {
        self::$rules[self::REQUEST_METHOD_PATCH.$pattern] = $action;
    }

    /**
     * 增加delete访问方式路由
     *
     * @param string $pattern 路由规则
     * @param string $action 执行的操作
     *
     * @return void
     */
    public static function delete($pattern, $action)
    {
        self::$rules[self::REQUEST_METHOD_DELETE.$pattern] = $action;
    }

    /**
     * 增加options访问方式路由
     *
     * @param string $pattern 路由规则
     * @param string $action 执行的操作
     *
     * @return void
     */
    public static function options($pattern, $action)
    {
        self::$rules[self::REQUEST_METHOD_OPTIONS.$pattern] = $action;
    }

    /**
     * 增加任意访问方式路由
     *
     * @param string $pattern 路由规则
     * @param string $action 执行的操作
     *
     * @return void
     */
    public static function any($pattern, $action)
    {
        self::$rules[self::REQUEST_METHOD_ANY.$pattern] = $action;
    }

    /**
     * 增加REST方式路由
     *
     * @param string $pattern 路由规则
     * @param string $action 执行的操作
     *
     * @return void
     */
    public static function rest($pattern, $action)
    {
        self::$rules[self::RESTROUTE.$pattern] = $action;
    }

    /**
     * 匹配路由
     *
     * @param string $pathinfo
     *
     * @return mixed
     */
    private static function isRoute(&$pathinfo)
    {
        empty($pathinfo) && $pathinfo[0] = '/';//网站根地址
        $issuccess = array();
        $route = self::$rules;
        isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] = self::REQUEST_METHOD_ANY;
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $rmethod = self::REQUEST_METHOD_GET;
                break;
            case 'POST':
                $rmethod = self::REQUEST_METHOD_POST;
                break;
            case 'PUT':
                $rmethod = self::REQUEST_METHOD_PUT;
                break;
            case 'PATCH':
                $rmethod = self::REQUEST_METHOD_PATCH;
                break;
            case 'DELETE':
                $rmethod = self::REQUEST_METHOD_DELETE;
                break;
            case 'OPTIONS':
                $rmethod = self::REQUEST_METHOD_OPTIONS;
                break;
            default :
                $rmethod = self::REQUEST_METHOD_ANY;
        }

        foreach ($route as $k => $v) {
            $rulesmethod = substr($k, 0, 1);
            if ($rulesmethod != $rmethod &&
                $rulesmethod != self::REQUEST_METHOD_ANY &&
                $rulesmethod != self::RESTROUTE) { //此条路由不符合当前请求方式
                continue;
            }
            unset($v);
            $singleRule = substr($k, 1);
            $arr = $singleRule === '/' ? array(0 => $singleRule) : explode('/', ltrim($singleRule, '/'));

            if ($arr[0] == $pathinfo[0]) {
                array_shift($arr);
                foreach ($arr as $key => $val) {
                    if (isset($pathinfo[$key + 1]) && $pathinfo[$key + 1] !== '') {
                        if (strpos($val, '\d') && !is_numeric($pathinfo[$key + 1])) {//数字变量
                            $route[$k] = false;//匹配失败
                            break 1;
                        } elseif (strpos($val, ':') === false && $val != $pathinfo[$key + 1]){//字符串
                            $route[$k] = false;//匹配失败
                            break 1;
                        }
                    } else {
                        $route[$k] = false;//匹配失败
                        break 1;
                    }
                }
            } else {
                $route[$k] = false;//匹配失败
            }

            if ($route[$k] !== false) {//匹配成功的路由
                $issuccess[] = $k;
            }
        }

        if (empty($issuccess)) {
            $returnArr[0] = false;
        } else {
            //匹配到多条路由时 选择最长的一条（匹配更精确）
            usort($issuccess, function($item1, $item2) {
                return strlen($item1) >= strlen($item2) ? 0 : 1;
            });

            if (is_callable($route[$issuccess[0]])) {
                call_user_func($route[$issuccess[0]]);
                Cml::cmlStop();
            }

            $route[$issuccess[0]] = trim($route[$issuccess[0]], '/');

            //判断路由的正确性
            count(explode('/', $route[$issuccess[0]])) >= 2 || throwException(Lang::get('_ROUTE_PARAM_ERROR_',  substr($issuccess[0], 1)));

            $returnArr[0] = true;
            $successRoute = explode('/', $issuccess[0]);
            foreach ($successRoute as $key => $val) {
                $t = explode('\d', $val);
                if (strpos($t[0], ':') !== false) {
                    $_GET[ltrim($t[0], ':')] = $pathinfo[$key];
                }
                unset($pathinfo[$key]);
            }

            if (substr($issuccess[0], 0 , 1) == self::RESTROUTE) {
                $actions = explode('/', $route[$issuccess[0]]);
                $arrKey = count($actions)-1;
                $actions[$arrKey] = strtolower($_SERVER['REQUEST_METHOD']) . ucfirst($actions[$arrKey]);
                $route[$issuccess[0]] = implode('/', $actions);
            }

            $returnArr['route'] = $route[$issuccess[0]];
        }
        return $returnArr;
    }

    /**
     * 获取解析后的pathinfo信息
     *
     * @return array
     */
    public static function getPathInfo()
    {
        return self::$pathinfo;
    }
}