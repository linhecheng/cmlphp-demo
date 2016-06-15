<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.51beautylife.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 15-1-25 下午3:07
 * @version  2.5
 * cml框架 锁机制驱动抽象类基类
 * *********************************************************** */
namespace Cml\Lock;

use Cml\Config;
use Cml\Model;

/**
 * 锁驱动抽象类基类
 *
 * @package Cml\Lock
 */
abstract class Base
{
    /**
     * 使用的缓存
     *
     * @var string
     */
    protected $userCache = 'default_cache';

    public function __construct($userCache) {
        is_null($userCache) || $this->userCache = $userCache;
    }

    /**
     * 锁的过期时间针对Memcache/Redis两种锁有效,File锁无效 单位s
     * 设为0时不过期。此时假如开发未手动unlock且这时出现程序挂掉的情况 __destruct未执行。这时锁必须人工介入处理
     * 这个值可根据业务需要进行修改比如60等
     *
     * @var int
     */
    protected $expire = 100;

    /**
     * 保存锁数据
     *
     * @var array
     */
    protected $lockCache = array();

    /**
     * 设置锁的过期时间
     *
     * @param int $expire
     *
     * @return \Cml\Lock\Redis | \Cml\Lock\Memcache | \Cml\Lock\File
     */
    public function setExpire($expire = 100)
    {
        $this->expire = $expire;
        return $this;
    }

    /**
     * 组装key
     *
     * @param string $key 要上的锁的key
     *
     * @return string
     */
    protected function getKey($key)
    {
        return Config::get('lock_prefix').$key;
    }

    /**
     * 上锁
     *
     * @param string $key 要解锁的锁的key
     * @param bool $wouldblock 是否堵塞
     *
     * @return mixed
     */
    abstract public function lock($key, $wouldblock = false);

    /**
     * 解锁
     *
     * @param string $key
     *
     * @return void
     */
    public function unlock($key)
    {
        $key = $this->getKey($key);

        if (
            isset($this->lockCache[$key])
            && $this->lockCache[$key] == Model::getInstance()->cache($this->userCache)->getInstance()->get($key)
        ) {
            Model::getInstance()->cache($this->userCache)->getInstance()->delete($key);
            $this->lockCache[$key] = null;//防止gc延迟,判断有误
            unset($this->lockCache[$key]);
        }
    }

    /**
     * 定义析构函数 自动释放获得的锁
     *
     */
    public function __destruct()
    {
        foreach ($this->lockCache as $key => $isMyLock) {
            if ($isMyLock == Model::getInstance()->cache($this->userCache)->getInstance()->get($key)) {
                Model::getInstance()->cache($this->userCache)->getInstance()->delete($key);
            }
            $this->lockCache[$key] = null;//防止gc延迟,判断有误
            unset($this->lockCache[$key]);
        }
    }
}