<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.51beautylife.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-8 下午3:07
 * @version  2.5
 * cml框架 缓存驱动抽象基类
 * *********************************************************** */
namespace Cml\Cache;

/**
 * 缓存驱动抽象基类
 *
 * @package Cml\Cache
 */
abstract class Base
{
    /**
     * @var bool|array
     */
    protected $conf;

    public function __get($var)
    {
        return $this->get($var);
    }

    public function __set($key, $val)
    {
        return $this->set($key, $val);
    }

    /**
     * 使用的缓存配置 默认为使用default_cache配置的参数
     *
     * @param bool｜array $conf
     */
    abstract public function __construct($conf = false);

    /**
     * 根据key取值
     *
     * @param mixed $key 要获取的缓存key
     *
     * @return mixed
     */
    abstract public function get($key);

    /**
     * 存储对象
     *
     * @param mixed $key 要缓存的数据的key
     * @param mixed $value 要缓存的值,除resource类型外的数据类型
     * @param int $expire 缓存的有效时间 0为不过期
     *
     * @return bool
     */
    abstract public function set($key, $value, $expire = 0);

    /**
     * 更新对象
     *
     * @param mixed $key 要更新的数据的key
     * @param mixed $value 要更新缓存的值,除resource类型外的数据类型
     * @param int $expire 缓存的有效时间 0为不过期
     *
     * @return bool|int
     */
    abstract public function update($key, $value, $expire = 0);

    /**
     * 删除对象
     *
     * @param mixed $key 要删除的数据的key
     *
     * @return bool
     */
    abstract public function delete($key);

    /**
     * 清洗已经存储的所有元素
     *
     * @return bool
     */
    abstract public function truncate();

    /**
     * 自增
     *
     * @param mixed $key 要自增的缓存的数据的key
     * @param int $val 自增的进步值,默认为1
     *
     * @return bool
     */
    abstract public function increment($key, $val = 1);

    /**
     * 自减
     *
     * @param mixed $key 要自减的缓存的数据的key
     * @param int $val 自减的进步值,默认为1
     *
     * @return bool
     */
    abstract public function decrement($key, $val = 1);

    /**
     * 返回实例便于操作未封装的方法
     *
     * @param string $key
     *
     * @return \Redis | \Memcache | \Memcached
     */
    abstract public function getInstance($key = '');

}