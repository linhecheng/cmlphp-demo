<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 15-1-25 下午3:07
 * @version  2.6
 * cml框架 锁机制Redis驱动
 * *********************************************************** */

namespace Cml\Lock;

use Cml\Cml;
use Cml\Model;

/**
 * 锁机制Redis驱动
 *
 * @package Cml\Lock
 */
class Redis extends Base
{
    /**
     * 上锁
     *
     * @param string $key 要上的锁的key
     * @param bool $wouldblock 是否堵塞
     *
     * @return mixed
     */
    public function lock($key, $wouldblock = false)
    {
        if(empty($key)) {
            return false;
        }
        $key = $this->getKey($key);

        if (
            isset($this->lockCache[$key])
            && $this->lockCache[$key] == Model::getInstance()->cache($this->userCache)->getInstance()->get($key)
        ) {
            return true;
        }

        if (Model::getInstance()->cache($this->userCache)->getInstance()->set(
            $key,
            Cml::$nowMicroTime,
            array('nx', 'ex' => $this->expire)
        )) {
            $this->lockCache[$key] = (string)Cml::$nowMicroTime;
            return true;
        }

        //非堵塞模式
        if (!$wouldblock) {
            return false;
        }

        //堵塞模式
        do {
            usleep(200);
        } while (!Model::getInstance()->cache($this->userCache)->getInstance()->set(
            $key,
            Cml::$nowMicroTime,
            array('nx', 'ex' => $this->expire)
        ));

        $this->lockCache[$key] = (string)Cml::$nowMicroTime;
        return true;
    }
}