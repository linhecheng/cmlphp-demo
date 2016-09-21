<?php namespace Cml\Queue;
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 16-02-04 下午20:11
 * @version  2.7
 * cml框架 队列基类
 * *********************************************************** */
use Cml\Interfaces\Queue;

/**
 * 队列基类
 *
 * @package Cml\Queue
 */
abstract class Base implements Queue
{
    /**
     * 序列化数据
     *
     * @param mixed $data
     *
     * @return string
     */
    protected function encodeDate($data)
    {
        return json_encode($data, PHP_VERSION >= '5.4.0' ? JSON_UNESCAPED_UNICODE : 0);
    }

    /**
     * 反序列化数据
     *
     * @param mixed $data
     *
     * @return string
     */
    protected function decodeDate($data)
    {
        return json_decode($data, true);
    }
}