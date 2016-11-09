<?php
/* * *********************************************************
 * [cmlphp] (C)2012 - 3000 http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 15-1-25 下午3:07
 * @version  @see \Cml\Cml::VERSION
 * cmlphp框架 锁机制File驱动
 * *********************************************************** */

namespace Cml\Lock;

use Cml\Cml;

/**
 * 锁机制File驱动
 *
 * @package Cml\Lock
 */
class File extends Base
{
    /**
     * 上锁
     *
     * @param string $key 要上的锁的key
     * @param bool $wouldBlock 是否堵塞
     *
     * @return mixed
     */
    public function lock($key, $wouldBlock = false)
    {
        if (empty($key)) {
            return false;
        }

        if (isset($this->lockCache[$key])) {//FileLock不支持设置过期时间
            return true;
        }

        $fileName = $this->getFileName($key);
        if (!$fp = fopen($fileName, 'w+')) {
            return false;
        }

        if (flock($fp, LOCK_EX | LOCK_NB)) {
            $this->lockCache[$fileName] = $fp;
            return true;
        }

        //非堵塞模式
        if (!$wouldBlock) {
            return false;
        }

        //堵塞模式
        do {
            usleep(200);
        } while (!flock($fp, LOCK_EX | LOCK_NB));

        $this->lockCache[$fileName] = $fp;
        return true;
    }

    /**
     * 解锁
     *
     * @param string $key 要解锁的锁的key
     */
    public function unlock($key)
    {
        $fileName = $this->getFileName($key);

        if (isset($this->lockCache[$fileName])) {
            flock($this->lockCache[$fileName], LOCK_UN);//5.3.2 在文件资源句柄关闭时不再自动解锁。现在要解锁必须手动进行。
            fclose($this->lockCache[$fileName]);
            is_file($fileName) && unlink($fileName);
            $this->lockCache[$fileName] = null;
            unset($this->lockCache[$fileName]);
        }
    }

    /**
     * 定义析构函数 自动释放获得的锁
     */
    public function __destruct()
    {
        foreach ($this->lockCache as $key => $fp) {
            flock($fp, LOCK_UN);//5.3.2 在文件资源句柄关闭时不再自动解锁。现在要解锁必须手动进行。
            fclose($fp);
            is_file($key) && unlink($key);
            $this->lockCache[$key] = null;//防止gc延迟,判断有误
            unset($this->lockCache[$key]);
        }
    }

    /**
     * 获取缓存文件名
     *
     * @param  string $key 缓存名
     *
     * @return string
     */
    private function getFileName($key)
    {
        $md5Key = md5($this->getKey($key));

        $dir = Cml::getApplicationDir('runtime_cache_path') . DIRECTORY_SEPARATOR . 'LockFileCache' . DIRECTORY_SEPARATOR . substr($key, 0, strrpos($key, '/')) . DIRECTORY_SEPARATOR;
        $dir .= substr($md5Key, 0, 2) . DIRECTORY_SEPARATOR . substr($md5Key, 2, 2);
        is_dir($dir) || mkdir($dir, 0700, true);
        return $dir . DIRECTORY_SEPARATOR . $md5Key . '.php';
    }
}
