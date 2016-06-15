<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.51beautylife.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-211 下午2:23
 * @version  2.5
 * cml框架 队列实现类
 * *********************************************************** */

namespace Cml\Vendor;

/**
 * 队列实现类
 *
 * @package Cml\Vendor
 */
class Quenue
{

    private static $queue = array(); //存放队列数据

    /**
     * 队列-设置值
     *
     * @param mixed $val 要入队的值
     *
     * @return bool
     */
    public function set($val)
    {
        array_unshift(self::$queue, $val);
        return true;
    }

    /**
     * 队列-从队列中获取一个最早放进队列的值
     *
     * @return string
     */
    public function get()
    {
        return array_pop(self::$queue);
    }

    /**
     * 队列-队列中总共有多少值
     *
     * @return string
     */
    public function count()
    {
        return count(self::$queue);
    }

    /**
     * 队列-清空队列数据
     *
     * @return string
     */
    public function clear()
    {
        return self::$queue = array();
    }
}