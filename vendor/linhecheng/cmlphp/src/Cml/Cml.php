<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.51beautylife.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-8 下午2:51
 * @version  2.5
 * cml框架 项目基类
 * *********************************************************** */
namespace Cml;

use Cml\Http\Request;
use Cml\Http\Response;
use Cml\Tools\RunCliCommand;

/**
 * 框架基础类,负责初始化应用的一系列工作,如配置初始化、语言包载入、错误异常机制的处理等
 *
 * @package Cml
 */
class Cml
{
    /**
     * 当前时间
     *
     * @var int
     */
    public static $nowTime = 0;

    /**
     * 当前时间含微秒
     *
     * @var int
     */
    public static $nowMicroTime = 0;

    /**
     * 致命错误捕获
     *
     */
    public static function fatalError()
    {
        if ($error = error_get_last()) {//获取最后一个发生的错误的信息。 包括提醒、警告、致命错误
            if (in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING))) { //当捕获到的错误为致命错误时 报告
                Plugin::hook('cml.before_fatal_error', $error);
                
                if (!$GLOBALS['debug']) {
                    //正式环境 只显示‘系统错误’并将错误信息记录到日志
                    Log::emergency('fatal_error', array($error));
                    $error = array();
                    $error['message'] = Lang::get('_CML_ERROR_');
                } else {
                    $error['files'][0] = array(
                        'file' => $error['file'],
                        'line' => $error['line']
                    );
                }

                if (Request::isCli()) {
                    pd($error);
                } else {
                    header('HTTP/1.1 500 Internal Server Error');
                    require Config::get('html_exception');
                }
            }
        }
        
        Plugin::hook('cml.before_cml_stop');
    }

    /**
     * 自定义异常处理
     *
     * @param mixed $e 异常对象
     */
    public static function appException($e)
    {
        Plugin::hook('cml.before_throw_exception', $e);

        $error = array();
        $error['message'] = $e->getMessage();
        $trace  =   $e->getTrace();
        foreach ($trace as $key => $val) {
            $error['files'][$key] = $val;
        }

        if (!$GLOBALS['debug']) {
            //正式环境 只显示‘系统错误’并将错误信息记录到日志
            Log::emergency($error['message'], array($error['files'][0]));

            $error = array();
            $error['message'] = Lang::get('_CML_ERROR_');
        }

        if (Request::isCli()) {
            pd($error);
        } else {
            header('HTTP/1.1 500 Internal Server Error');
            require Config::get('html_exception');
        }
    }

    /**
     * 自动加载类库
     * 要注意的是 使用autoload的时候  不能手动抛出异常
     * 因为在自动加载静态类时手动抛出异常会导致自定义的致命错误捕获机制和自定义异常处理机制失效
     * 而 new Class 时自动加载不存在文件时，手动抛出的异常可以正常捕获
     * 这边即使文件不存在时没有抛出自定义异常也没关系，因为自定义的致命错误捕获机制会捕获到错误
     *
     * @param string $className
     */
    public static function autoloadComposerAdditional($className)
    {
        $GLOBALS['debug'] && Debug::addTipInfo(Lang::get('_CML_DEBUG_ADD_CLASS_TIP_', $className), 1);//在debug中显示包含的类
    }

    /**
     * 处理配置及语言包相关
     *
     */
    private static function handleConfigLang()
    {
        //因自动加载机制需要\Cml\Config和\Cml\Lang的支持所以手动载入这两个类
        require CML_PATH.DIRECTORY_SEPARATOR.'Cml'.DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Request.php';
        require CML_PATH.DIRECTORY_SEPARATOR.'Cml'.DIRECTORY_SEPARATOR.'Config.php';
        require CML_PATH.DIRECTORY_SEPARATOR.'Cml'.DIRECTORY_SEPARATOR.'Lang.php';

        //引入框架惯例配置文件
        $cmlConfig = require CML_PATH.DIRECTORY_SEPARATOR.'Cml'.DIRECTORY_SEPARATOR.'Config'.DIRECTORY_SEPARATOR.'config.php';
        Config::init();

        //应用正式配置文件
        $appConfig = CML_APP_FULL_PATH.DIRECTORY_SEPARATOR.'Config'.DIRECTORY_SEPARATOR.Config::$isLocal.DIRECTORY_SEPARATOR.'normal.php';

        is_file($appConfig) ? $appConfig = require $appConfig
            : exit('Config File ['.Config::$isLocal.'/normal.php] Not Found Please Check！');
        is_array($appConfig) || $appConfig = array();

        $commonConfig = CML_APP_FULL_PATH.DIRECTORY_SEPARATOR.'Config'.DIRECTORY_SEPARATOR.'common.php';
        $commonConfig = is_file($commonConfig) ? require $commonConfig : array();

        Config::set(array_merge($cmlConfig, $commonConfig, $appConfig));//合并配置

        define('CML_IS_MULTI_MODULES', Config::get('is_multi_modules'));
        define(
            'CML_APP_MODULES_PATH',
            CML_APP_FULL_PATH . (CML_IS_MULTI_MODULES ? DIRECTORY_SEPARATOR . \Cml\Config::get('application_dir') : '')
        );

        //引入系统语言包
        Lang::set(require( (CML_PATH.DIRECTORY_SEPARATOR.'Cml'.DIRECTORY_SEPARATOR.'Lang'.DIRECTORY_SEPARATOR.Config::get('lang').'.php') ));
    }

    /**
     * 初始化运行环境
     *
     */
    private static function init()
    {
        header('X-Powered-By:CmlPHP');
        define('CML_PATH', dirname(__DIR__)); //框架的路径

        //设置框架所有需要的路径
        define('CML_APP_FULL_PATH', CML_PROJECT_PATH . DIRECTORY_SEPARATOR . CML_APP_PATH);
        define('CML_RUNTIME_PATH', CML_APP_FULL_PATH.DIRECTORY_SEPARATOR.'Runtime');
        define('CML_EXTEND_PATH', CML_PATH.DIRECTORY_SEPARATOR.'Vendor');// 系统扩展类库目录

        //设置运行时文件路径
        define('CML_RUNTIME_CACHE_PATH', CML_RUNTIME_PATH.DIRECTORY_SEPARATOR.'Cache'); //系统缓存目录
        define('CML_RUNTIME_LOGS_PATH', CML_RUNTIME_PATH.DIRECTORY_SEPARATOR.'Logs');  //系统日志目录
        define('CML_RUNTIME_DATA_PATH',  CML_RUNTIME_PATH.DIRECTORY_SEPARATOR.'Data');//数据表的结构文件

        self::handleConfigLang();

        date_default_timezone_set(Config::get('time_zone')); //设置时区

        self::$nowTime = time();
        self::$nowMicroTime = microtime(true);

        //包含框架中的框架函数库文件
        require CML_PATH.DIRECTORY_SEPARATOR.'Cml'.DIRECTORY_SEPARATOR.'Tools'.DIRECTORY_SEPARATOR.'functions.php';

        // 注册AUTOLOAD方法
        //spl_autoload_register('Cml\Cml::autoload');

        //设置自定义捕获致命异常函数
        //普通错误由Cml\Debug::catcher捕获 php默认在display_errors为On时致命错误直接输出 为off时 直接显示服务器错误或空白页,体验不好
        register_shutdown_function('Cml\Cml::fatalError'); //捕获致命异常

        //设置自定义的异常处理函数。
        set_exception_handler('Cml\Cml::appException'); //手动抛出的异常由此函数捕获

        ini_set('display_errors', 'off');//屏蔽系统自带的错误输出

        //程序运行必须的类
        $runTimeClassList = array(
            CML_PATH.DIRECTORY_SEPARATOR.'Cml'.DIRECTORY_SEPARATOR.'Controller.php',
            CML_PATH.DIRECTORY_SEPARATOR.'Cml'.DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Response.php',
            CML_PATH.DIRECTORY_SEPARATOR.'Cml'.DIRECTORY_SEPARATOR.'Route.php',
            CML_PATH.DIRECTORY_SEPARATOR.'Cml'.DIRECTORY_SEPARATOR.'Secure.php',
        );
        Config::get('session_user') && $runTimeClassList[] = CML_PATH.DIRECTORY_SEPARATOR.'Cml'.DIRECTORY_SEPARATOR.'Session.php';

        //设置调试模式
        if (Config::get('debug')) {
            $GLOBALS['debug'] = true;//开启debug
            Debug::start();//记录开始运行时间\内存初始使用
            //设置捕获系统异常 使用set_error_handler()后，error_reporting将会失效。所有的错误都会交给set_error_handler。
            set_error_handler('\Cml\Debug::catcher');

            spl_autoload_register('Cml\Cml::autoloadComposerAdditional', true, true);

            //包含程序运行必须的类
            foreach ($runTimeClassList as $file) {
                require $file;
                Debug::addTipInfo(Lang::get('_CML_DEBUG_ADD_CLASS_TIP_', 'Cml\\'.basename($file)), 1);
            }
            Debug::addTipInfo(Lang::get('_CML_DEBUG_ADD_CLASS_TIP_', 'Cml\\Debug'), 1);
            $runTimeClassList = null;

            Debug::addTipInfo(Lang::get('_CML_DEBUG_ADD_CLASS_TIP_', 'Cml\Cml'), 1);
            Debug::addTipInfo(Lang::get('_CML_DEBUG_ADD_CLASS_TIP_', 'Cml\Config'), 1);
            Debug::addTipInfo(Lang::get('_CML_DEBUG_ADD_CLASS_TIP_', 'Cml\Lang'), 1);
            Debug::addTipInfo(Lang::get('_CML_DEBUG_ADD_CLASS_TIP_', 'Cml\Http\Request'), 1);
        } else {
            $GLOBALS['debug'] = false;//关闭debug
            //ini_set('error_reporting', E_ALL & ~E_NOTICE);//记录除了notice之外的错误
            ini_set('log_errors', 'off'); //关闭php自带错误日志
            //严重错误已经通过fatalError记录。为了防止日志过多,默认不记录致命错误以外的日志。有需要可以修改配置开启
            if (Config::get('log_warn_log')) {
                set_error_handler('\Cml\Log::catcherPhpError');
            }

            //线上模式包含runtime.php
            $runTimeFile = CML_RUNTIME_PATH.DIRECTORY_SEPARATOR.'_runtime_.php';
            if (!is_file($runTimeFile)) {
                $runTimeContent = '<?php';
                foreach ($runTimeClassList as $file) {
                    $runTimeContent .= str_replace(array('<?php', '?>'), '', php_strip_whitespace($file));
                }
                file_put_contents($runTimeFile, $runTimeContent, LOCK_EX);
                $runTimeContent = null;
            }
            require $runTimeFile;
        }

        // 页面压缩输出支持
        if (Config::get('output_encode')) {
            $zlib = ini_get('zlib.output_compression');
            if (empty($zlib)) {
                ///@ob_end_clean () ; //防止在启动ob_start()之前程序已经有输出(比如配置文件尾多敲了换行)会导致服务器303错误
                ob_start('ob_gzhandler') || ob_start();
                define('CML_OB_START', true);
            } else {
                define('CML_OB_START', false);
            }
        }

        //包含应用函数库文件 都使用composer去管理
        //$projectFuns = CML_APP_FULL_PATH.DIRECTORY_SEPARATOR.'Function'.DIRECTORY_SEPARATOR.'function.php';
        //is_file($projectFuns) && require $projectFuns;

        //载入插件配置文件
        $pluginConfig = CML_APP_FULL_PATH . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'plugin.php';
        is_file($pluginConfig) && require $pluginConfig;

        Request::isCli() && RunCliCommand::runCliCommand();

        Plugin::hook('cml.before_parse_url');

        //载入路由
        $routeConfigFile = CML_APP_FULL_PATH.DIRECTORY_SEPARATOR.'Config'.DIRECTORY_SEPARATOR.'route.php';
        is_file($routeConfigFile) && require $routeConfigFile;
        Route::parseUrl();    //解析处理URL

        Plugin::hook('cml.after_parse_url');

        //载入模块配置
        if (CML_IS_MULTI_MODULES) {
            $modulesConfig = CML_APP_MODULES_PATH . Route::$urlParams['path'] . 'Config' . DIRECTORY_SEPARATOR . 'normal.php';
            is_file($modulesConfig) && Config::set(require $modulesConfig);

            //载入模块语言包
            $appLang = CML_APP_MODULES_PATH . Route::$urlParams['path'].'Lang'.DIRECTORY_SEPARATOR.Config::get('lang').'.php';
            is_file($appLang) && Lang::set(require($appLang));
        }

        //设置应用路径
        define('CML_APP_CONTROLLER_PATH', CML_APP_MODULES_PATH.
            (
                CML_IS_MULTI_MODULES ?
                Route::$urlParams['path'] . 'Controller' . DIRECTORY_SEPARATOR
                : DIRECTORY_SEPARATOR . 'Controller' . Route::$urlParams['path']
            )
        );
    }

    /**
     * 某些场景(如：跟其它项目混合运行的时候)只希望使用CmlPHP中的组件而不希望运行控制器，用来替代runApp
     *
     *
     */
    public static function onlyInitEnvironmentNotRunController()
    {
        self::init();
    }

    /**
     * 启动框架
     *
     */
    public static function runApp()
    {
        //系统初始化
        self::init();

        //控制器所在路径
        $actionController = CML_APP_CONTROLLER_PATH . Route::$urlParams['controller'] . 'Controller.php';

        $GLOBALS['debug'] && Debug::addTipInfo(Lang::get('_CML_ACTION_CONTROLLER_', $actionController));

        Plugin::hook('cml.before_run_controller');

        if (is_file($actionController)) {
            $className = Route::$urlParams['controller'].'Controller';
            $className = (CML_IS_MULTI_MODULES ? '' : '\Controller')
                .Route::$urlParams['path'].
                (CML_IS_MULTI_MODULES ? 'Controller'.DIRECTORY_SEPARATOR : '').
                "{$className}";
            $className = str_replace('/', '\\', $className);

            $controller = new $className();
            call_user_func(array( $controller ,  "runAppController"));//运行
        } else {
            self::montFor404Page();
            if ($GLOBALS['debug']) {
                throwException(Lang::get(
                    '_CONTROLLER_NOT_FOUND_',
                    CML_APP_CONTROLLER_PATH,
                    Route::$urlParams['controller'],
                    str_replace('/', '\\', Route::$urlParams['path']).Route::$urlParams['controller']
                ));
            } else {
                Response::show404Page();
            }
        }

        //输出Debug模式的信息
        self::cmlStop();
    }

    /**
     * 未找到控制器的时候设置勾子
     *
     */
    public static function montFor404Page()
    {
        Plugin::mount('cml.before_show_404_page', array(
            function() {
                $cmdLists = Config::get('cmlframework_system_command');
                $cmd = strtolower(trim(Route::$urlParams['path'], DIRECTORY_SEPARATOR));
                if (isset($cmdLists[$cmd])) {
                    call_user_func($cmdLists[$cmd]);
                }
            }
        ));
        Plugin::hook('cml.before_show_404_page');
    }

    /**
     * 程序中并输出调试信息
     *
     */
    public static function cmlStop()
    {
        //输出Debug模式的信息
        if ($GLOBALS['debug']) {
            header('Content-Type:text/html; charset='.Config::get('default_charset'));
            Debug::stop();
        } else {
            $deBugLogData = dump('', 1);
            if (!empty($deBugLogData)) {
                Config::get('dump_use_php_console') ? \Cml\dumpUsePHPConsole($deBugLogData) : require CML_PATH.DIRECTORY_SEPARATOR.'Cml'.DIRECTORY_SEPARATOR.'ConsoleLog.php';
            };
            CML_OB_START && ob_end_flush();
            exit();
        }
    }

    /**
     * 以.的方式获取数组的值
     *
     * @param string $key
     * @param array $arr
     * @param null $default
     *
     * @return null
     */
    public static function doteToArr($key = '', &$arr = array(), $default = null)
    {
        if (!strpos($key, '.')) {
            return isset($arr[$key]) ? $arr[$key] : $default;
        }

        // 获取多维数组
        $key = explode('.', $key);
        $tmp = null;
        foreach ($key as $k) {
            if (is_null($tmp)) {
                if (isset($arr[$k])) {
                    $tmp = $arr[$k];
                } else {
                    return $default;
                }
            } else {
                if (isset($tmp[$k])) {
                    $tmp = $tmp[$k];
                } else {
                    return $default;
                }
            }
        }
        return $tmp;
    }

    /**
     * 是否开启全局紧急模式
     *
     * @return bool
     */
    public static function isEmergencyMode()
    {
        return Config::get('emergency_mode_not_real_time_refresh_mysql_query_cache') !== false;
    }

}