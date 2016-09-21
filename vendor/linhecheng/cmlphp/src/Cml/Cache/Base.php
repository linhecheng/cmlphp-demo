<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-8 下午3:07
 * @version  2.7
 * cml框架 缓存驱动抽象基类
 * *********************************************************** */
namespace Cml\Cache;
use Cml\Interfaces\Cache;

/**
 * 缓存驱动抽象基类
 *
 * @package Cml\Cache
 */
abstract class Base implements Cache
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
}