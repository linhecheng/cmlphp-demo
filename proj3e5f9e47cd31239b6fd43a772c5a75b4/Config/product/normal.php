<?php
//配置文件
defined('CML_PATH') || exit();

$config = array(
    'default_db' => array(
        'driver' => 'MySql.Pdo', //数据库驱动
        'master' => array(
            'host' => 'localhost', //数据库主机
            'username' => 'root', //数据库用户名
            'password' => '', //数据库密码
            'dbname' => 'cmlphp', //数据库名
            'charset' => 'utf8', //数据库编码
            'tableprefix' => 'cml_', //数据表前缀
            'pconnect' => false, //是否开启数据库长连接
            'engine' => ''//数据库引擎
        ),
        'slaves' => array(),//从库配置
        'cache_expire' => 3600,//查询数据缓存时间
    ),
    // 缓存服务器的配置
    'default_cache' => array(
        'on' => 0, //为1则启用，或者不启用
        'driver' => 'Memcache',
        'prefix' => 'p_c_fev_',
        'server' => array(
            array(
                'host' => '127.0.0.1',
                'port' => 11211
            ),
            //多台...
        ),
    ),
    'lock_prefix' => 'd_l_0ce_',//加锁前缀
);
return $config;