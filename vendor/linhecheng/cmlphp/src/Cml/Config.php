<?php
/* * *********************************************************
 * [cmlphp] (C)2012 - 3000 http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-13 上午11:01
 * @version  @see \Cml\Cml::VERSION
 * cmlphp框架 配置处理类
 * *********************************************************** */
namespace Cml;

use Cml\Exception\ConfigNotFoundException;
use Cml\Http\Request;

/**
 * 配置读写类、负责配置文件的读取
 *
 * @package Cml
 */
class Config
{
    /**
     * 配置文件类型
     *
     * @var string
     */
    public static $isLocal = 'product';

    /**
     * 存放了所有配置信息
     *
     * @var array
     */
    private static $_content = [
        'normal' => []
    ];

    public static function init()
    {
        if (Request::isCli()) {
            self::$isLocal = 'cli';
            return true;
        }

        switch ($_SERVER['HTTP_HOST']) {
            case $_SERVER['SERVER_ADDR'] :
                // no break
            case '127.0.0.1':
                //no break
            case 'localhost':
                self::$isLocal = 'development';
                return true;

        }

        if (isset($_SERVER['SERVER_NAME'])) {
            $host = $_SERVER['SERVER_NAME'];
        } else {
            $host = $_SERVER['HTTP_HOST'];
            if ($_SERVER['SERVER_PORT'] != 80) {
                $host = explode(':', $host);
                $host = $host[0];
            }
        }

        $domain = substr($host, strrpos($host, '.') + 1);

        if ($domain == 'dev' || $domain == 'loc') {
            self::$isLocal = 'development';
            return true;
        }

        if (substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], '.')) == '192') {
            self::$isLocal = 'development';
        }
        return true;
    }

    /**
     * 获取配置参数不区分大小写
     *
     * @param string $key 支持.获取多维数组
     * @param string $default 不存在的时候默认值
     *
     * @return mixed
     */
    public static function get($key = null, $default = null)
    {
        // 无参数时获取所有
        if (empty($key)) {
            return self::$_content;
        }

        $key = strtolower($key);
        return Cml::doteToArr($key, self::$_content['normal'], $default);
    }

    /**
     * 设置配置【语言】 支持批量设置 /a.b.c方式设置
     *
     * @param string|array $key 要设置的key,为数组时是批量设置
     * @param mixed $value 要设置的值
     *
     * @return null
     */
    public static function set($key, $value = null)
    {
        if (is_array($key)) {
            static::$_content['normal'] = array_merge(static::$_content['normal'], array_change_key_case($key));
        } else {
            $key = strtolower($key);

            if (!strpos($key, '.')) {
                static::$_content['normal'][$key] = $value;
                return null;
            }

            // 多维数组设置 A.B.C = 1
            $key = explode('.', $key);
            $tmp = null;
            foreach ($key as $k) {
                if (is_null($tmp)) {
                    if (isset(static::$_content['normal'][$k]) === false) {
                        static::$_content['normal'][$k] = [];
                    }
                    $tmp = &static::$_content['normal'][$k];
                } else {
                    is_array($tmp) || $tmp = [];
                    isset($tmp[$k]) || $tmp[$k] = [];
                    $tmp = &$tmp[$k];
                }
            }
            $tmp = $value;
            unset($tmp);
        }
        return null;
    }

    /**
     * 从文件载入Config
     *
     * @param string $file
     * @param bool $global 是否从全局加载
     *
     * @return array
     */
    public static function load($file, $global = true)
    {
        if (isset(static::$_content[$file])) {
            return static::$_content[$file];
        } else {
            $file =
                (
                $global
                    ? Cml::getApplicationDir('global_config_path')
                    : Cml::getApplicationDir('apps_path')
                    . '/' . Cml::getContainer()->make('cml_route')->getAppName() . '/'
                    . Cml::getApplicationDir('app_config_path_name')
                )
                . '/' . ($global ? self::$isLocal . DIRECTORY_SEPARATOR : '') . $file . '.php';

            if (!is_file($file)) {
                throw new ConfigNotFoundException(Lang::get('_NOT_FOUND_', $file));
            }
            static::$_content[$file] = Cml::requireFile($file);
            return static::$_content[$file];
        }
    }
}
