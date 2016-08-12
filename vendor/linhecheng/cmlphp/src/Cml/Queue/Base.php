<?php namespace Cml\Queue;
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 16-02-04 下午20:11
 * @version  2.6
 * cml框架 队列基类
 * *********************************************************** */
/**
 * 队列基类
 *
 * @package Cml\Queue
 */
abstract class Base
{

    /**
     * 从列表头入队
     *
     * @param string $name 要从列表头入队的队列的名称
     * @param mixed $data 要入队的数据
     *
     * @return mixed
     */
    abstract public function lPush($name, $data);

    /**
     * 从列表头出队
     *
     * @param string $name 要从列表头出队的队列的名称
     *
     * @return mixed
     */
    abstract public function lPop($name);

    /**
     * 从列表尾入队
     *
     * @param string $name 要从列表尾入队的队列的名称
     * @param mixed $data 要入队的数据
     *
     * @return mixed
     */
    abstract public function rPush($name, $data);

    /**
     * 从列表尾出队
     *
     * @param string $name 要从列表尾出队的队列的名称
     *
     * @return mixed
     */
    abstract public function rPop($name);

    /**
     * 弹入弹出
     *
     * @param string $from 要弹出的队列名称
     * @param string $to 要入队的队列名称
     *
     * @return mixed
     */
    abstract public function rPopLpush($from, $to);

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