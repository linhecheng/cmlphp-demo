<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 16-9-6 下午3:07
 * @version  2.7
 * cml框架 异常/错误接口
 * *********************************************************** */
namespace Cml\Interfaces;

/**
 * 系统错误及异常捕获驱动抽象接口
 *
 * @package Cml\Interfaces
 */
interface ErrorOrException
{
    /**
     * 致命错误捕获
     *
     * @param  array $error 错误信息
     */
    public function fatalError(&$error);

    /**
     * 自定义异常处理
     *
     * @param mixed $e 异常对象
     */
    public function appException(&$e);
}