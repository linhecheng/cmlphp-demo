<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.51beautylife.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-8 下午3:07
 * @version  2.5
 * cml框架 Redis缓存驱动
 * *********************************************************** */
namespace Cml\Cache;

use Cml\Config;
use Cml\Lang;

/**
 * Redis缓存驱动
 *
 * @package Cml\Cache
 */
class Redis extends namespace\Base
{
    /**
     * @var array(\Redis)
     */
    private $redis = array();

    /**
     * 使用的缓存配置 默认为使用default_cache配置的参数
     *
     * @param bool｜array $conf
     */
    public function __construct($conf = false)
    {
        $this->conf = $conf ? $conf : Config::get('default_cache');

        if (!extension_loaded('redis') ) {
            \Cml\throwException(Lang::get('_CACHE_EXTEND_NOT_INSTALL_', 'Redis'));
        }
    }

    /**
     * 根据key获取redis实例
     * 这边还是用取模的方式，一致性hash用php实现性能开销过大。取模的方式对只有几台机器的情况足够用了
     * 如果有集群需要，直接使用redis3.0+自带的集群功能就好了。不管是可用性还是性能都比用php自己实现好
     *
     * @param $key
     *
     * @return \Redis
     */
    private function hash($key) {
        $success = sprintf('%u', crc32($key)) % count($this->conf['server']);

        if(!isset($this->redis[$success]) || !is_object($this->redis[$success])) {
            $instance = new \Redis();
            if($instance->pconnect($this->conf['server'][$success]['host'], $this->conf['server'][$success]['port'], 1.5)) {
                $this->redis[$success] = $instance;
            } else {
                \Cml\throwException(Lang::get('_CACHE_CONNECT_FAIL_', 'Redis',
                    $this->conf['server'][$success]['host'] . ':' . $this->conf['server'][$success]['port']
                ));
            }

            if (isset($this->conf['server'][$success]['password']) && !empty($this->conf['server'][$success]['password'])) {
                $instance->auth($this->conf['server'][$success]['password']) || \Cml\throwException('redis password error!');
            }
        }
        return $this->redis[$success];
    }

    /**
     * 根据key取值
     *
     * @param mixed $key 要获取的缓存key
     *
     * @return bool | array
     */
    public function get($key)
    {
        $return = json_decode($this->hash($key)->get($this->conf['prefix'] . $key), true);
        is_null($return) && $return = false;
        return $return; //orm层做判断用
    }

    /**
     * 存储对象
     *
     * @param mixed $key 要缓存的数据的key
     * @param mixed $value 要缓存的值,除resource类型外的数据类型
     * @param int $expire 缓存的有效时间 0为不过期
     *
     * @return bool
     */
    public function set($key, $value, $expire = 0)
    {
        $value = json_encode($value, PHP_VERSION >= '5.4.0' ? JSON_UNESCAPED_UNICODE : 0);
        if ($expire > 0) {
            return $this->hash($key)->setex($this->conf['prefix'] . $key, $expire, $value);
        } else {
            return $this->hash($key)->set($this->conf['prefix'] . $key, $value);
        }
    }

    /**
     * 更新对象
     *
     * @param mixed $key 要更新的数据的key
     * @param mixed $value 要更新缓存的值,除resource类型外的数据类型
     * @param int $expire 缓存的有效时间 0为不过期
     *
     * @return bool|int
     */
    public function update($key, $value, $expire = 0)
    {
        $array = $this->get($key);
        if (!empty($array)) {
            return $this->set($key, array_merge($array, $value), $expire);
        }
        return 0;
    }

    /**
     * 删除对象
     *
     * @param mixed $key 要删除的数据的key
     *
     * @return bool
     */
    public function delete($key)
    {
        return $this->hash($key)->del($this->conf['prefix'] . $key);
    }

    /**
     * 清洗已经存储的所有元素
     *
     */
    public function truncate()
    {
        foreach ($this->conf['server'] as $key => $val) {
            if(!isset($this->redis[$key]) || !is_object($this->redis[$key])) {
                $instance = new \Redis();
                if($instance->pconnect($val['host'], $val['port'], 1.5)) {
                    $this->redis[$key] = $instance;
                } else {
                    \Cml\throwException(Lang::get('_CACHE_NEW_INSTANCE_ERROR_', 'Redis'));
                }
            }
            $this->redis[$key]->flushDB();
        }
        return true;
    }

    /**
     * 自增
     *
     * @param mixed $key 要自增的缓存的数据的key
     * @param int $val 自增的进步值,默认为1
     *
     * @return bool
     */
    public function increment($key, $val = 1)
    {
        $val = abs(intval($val));
        if ($val === 1) {
            return $this->hash($key)->incr($this->conf['prefix'] . $key);
        } else {
            $return = true;
            for($i = 0; $i < $val; $i++) {
                if (!$this->hash($key)->incr($this->conf['prefix'] . $key)) {
                    $return = false;
                }
            }
            return $return;
        }
    }

    /**
     * 自减
     *
     * @param mixed $key 要自减的缓存的数据的key
     * @param int $val 自减的进步值,默认为1
     *
     * @return bool
     */
    public function decrement($key, $val = 1)
    {
        $val = abs(intval($val));
        if ($val === 1) {
            return $this->hash($key)->decr($this->conf['prefix'] . $key);
        } else {
            $return = true;
            for($i = 0; $i < $val; $i++) {
                if (!$this->hash($key)->decr($this->conf['prefix'] . $key)) {
                    $return = false;
                }
            }
            return $return;
        }

    }

    /**
     * 判断key值是否存在
     *
     * @param mixed $key 要判断的缓存的数据的key
     *
     * @return mixed
     */
    public function exists($key)
    {
        return $this->hash($key)->exists($this->conf['prefix'] . $key);
    }

    /**
     * 返回实例便于操作未封装的方法
     *
     * @param string $key
     *
     * @return \Redis
     */
    public function getInstance($key = '')
    {
        return $this->hash($key);
    }

}