<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.51beautylife.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-8 下午3:07
 * @version  2.5
 * cml框架 Log处理类
 * *********************************************************** */
namespace Cml;

use Cml\Logger\Base;

/**
 * Log处理类,简化的psr-3日志接口,负责Log的处理
 *
 * @package Cml
 */
class Log
{
    /**
     * 获取Logger实例
     *
     * @param string | null $logger 使用的log驱动
     *
     * @return Base
     */
    private static function getLogger($logger = null)
    {
        static $instance = null;
        if(is_null($instance)) {
            $driver = '\\Cml\\Logger\\' . (is_null($logger) ? Config::get('log_driver', 'File') : $logger);
            $instance = new $driver();
        }
        return $instance;
    }

    /**
     * 添加debug类型的日志
     *
     * @param string $log 要记录到log的信息
     * @param array $context 上下文信息
     *
     * @return bool
     */
    public static function debug($log, array $context = array())
    {
        return self::getLogger()->debug($log, $context);
    }

    /**
     * 添加info类型的日志
     *
     * @param string $log 要记录到log的信息
     * @param array $context 上下文信息
     *
     * @return bool
     */
    public static function info($log, array $context = array())
    {
        return self::getLogger()->info($log, $context);
    }

    /**
     * 添加notice类型的日志
     *
     * @param string $log 要记录到log的信息
     * @param array $context 上下文信息
     *
     * @return bool
     */
    public static function notice($log, array $context = array())
    {
        return self::getLogger()->notice($log, $context);
    }

    /**
     * 添加warning类型的日志
     *
     * @param string $log 要记录到log的信息
     * @param array $context 上下文信息
     *
     * @return bool
     */
    public static function warning($log, array $context = array())
    {
        return self::getLogger()->warning($log, $context);
    }

    /**
     * 添加error类型的日志
     *
     * @param string $log 要记录到log的信息
     * @param array $context 上下文信息
     *
     * @return bool
     */
    public static function error($log, array $context = array())
    {
        return self::getLogger()->error($log, $context);
    }

    /**
     * 添加critical类型的日志
     *
     * @param string $log 要记录到log的信息
     * @param array $context 上下文信息
     *
     * @return bool
     */
    public static function critical($log, array $context = array())
    {
        return self::getLogger()->critical($log, $context);
    }

    /**
     * 添加critical类型的日志
     *
     * @param string $log 要记录到log的信息
     * @param array $context 上下文信息
     *
     * @return bool
     */
    public static function emergency($log, array $context = array())
    {
        return self::getLogger()->emergency($log, $context);
    }

    /**
     * 错误日志handler
     *
     * @param int $errno 错误类型 分运行时警告、运行时提醒、自定义错误、自定义提醒、未知等
     * @param string $errstr 错误提示
     * @param string $errfile 发生错误的文件
     * @param string $errline 错误所在行数
     *
     * @return void
     */
    public static function catcherPhpError($errno, $errstr, $errfile, $errline)
    {
        if (in_array($errno, array(E_NOTICE, E_STRICT, E_DEPRECATED, E_USER_DEPRECATED, E_USER_NOTICE))) {
            return ;//只记录warning以上级别日志
        }

        self::getLogger()->log(self::getLogger()->phpErrorToLevel[$errno], $errstr, array('file' => $errfile, 'line' => $errline));
        return;
    }

}