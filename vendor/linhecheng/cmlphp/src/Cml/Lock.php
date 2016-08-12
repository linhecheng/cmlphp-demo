<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 16-4-15
 * @version  2.6
 * cml框架 Lock处理类
 * *********************************************************** */
namespace Cml;

/**
 * Lock处理类提供统一的锁机制
 *
 * @package Cml
 */
class Lock
{
    /**
     * 获取Lock实例
     *
     * @param string|null $useCache 使用的锁的配置
     *
     * @return \Cml\Lock\Redis | \Cml\Lock\Memcache | \Cml\Lock\File | false
     * @throws \Exception
     */
    public static function getLocker($useCache = null)
    {
        is_null($useCache) && $useCache = Config::get('locker_use_cache', 'default_cache');
        static $_instance = array();
        $config = Config::get($useCache);
        if (isset($_instance[$useCache])) {
            return $_instance[$useCache];
        } else {
            if ($config['on']) {
                $lock = 'Cml\Lock\\'.$config['driver'];
                $_instance[$useCache] = new $lock($useCache);
                return $_instance[$useCache];
            } else {
                throw new \InvalidArgumentException(Lang::get('_NOT_OPEN_', $useCache));
            }
        }
    }
}
