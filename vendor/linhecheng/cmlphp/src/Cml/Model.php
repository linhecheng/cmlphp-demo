<?php
/* * *********************************************************
 * [cmlphp] (C)2012 - 3000 http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-8 下午3:07
 * @version  @see \Cml\Cml::VERSION
 * cmlphp框架 系统默认Model
 * *********************************************************** */
namespace Cml;

/**
 * 基础Model类，在CmlPHP中负责数据的存取(目前包含db/cache)
 * @package Cml
 */
class Model
{
    /**
     * 表前缀
     *
     * @var null|string
     */
    protected $tablePrefix = null;

    /**
     * 表名
     *
     * @var null|string
     */
    protected $table = null;

    /**
     * Db驱动实例
     *
     * @var array
     */
    private static $dbInstance = [];

    /**
     * Cache驱动实例
     *
     * @var array
     */
    private static $cacheInstance = [];

    /**
     * 获取db实例
     *
     * @param string $conf 使用的数据库配置;
     *
     * @return \Cml\Db\MySql\Pdo | \Cml\Db\MongoDB\MongoDB | \Cml\Db\Base
     */
    public function db($conf = '')
    {
        $conf == '' && $conf = $this->getDbConf();
        if (is_array($conf)) {
            $config = $conf;
            $conf = md5(json_encode($conf));
        } else {
            $config = Config::get($conf);
        }
        $config['mark'] = $conf;

        if (isset(self::$dbInstance[$conf])) {
            return self::$dbInstance[$conf];
        } else {
            $pos = strpos($config['driver'], '.');
            self::$dbInstance[$conf] = Cml::getContainer()->make('db_' . strtolower($pos ? substr($config['driver'], 0, $pos) : $config['driver']), $config);
            return self::$dbInstance[$conf];
        }
    }

    /**
     * 当程序连接N个db的时候用于释放于用连接以节省内存
     *
     * @param string $conf 使用的数据库配置;
     */
    public function closeDb($conf = 'default_db')
    {
        //$this->db($conf)->close();释放对象时会执行析构回收
        unset(self::$dbInstance[$conf]);
    }

    /**
     * 获取cache实例
     *
     * @param string $conf 使用的缓存配置;
     *
     * @return \Cml\Cache\Redis | \Cml\Cache\Apc | \Cml\Cache\File | \Cml\Cache\Memcache
     */
    public function cache($conf = 'default_cache')
    {
        if (is_array($conf)) {
            $config = $conf;
            $conf = md5(json_encode($conf));
        } else {
            $config = Config::get($conf);
        }

        if (isset(self::$cacheInstance[$conf])) {
            return self::$cacheInstance[$conf];
        } else {
            if ($config['on']) {
                self::$cacheInstance[$conf] = Cml::getContainer()->make('cache_' . strtolower($config['driver']), $config);
                return self::$cacheInstance[$conf];
            } else {
                throw new \InvalidArgumentException(Lang::get('_NOT_OPEN_', $conf));
            }
        }
    }

    /**
     * 初始化一个Model实例
     *
     * @return \Cml\Model | \Cml\Db\MySql\Pdo | \Cml\Db\MongoDB\MongoDB | \Cml\Db\Base
     */
    public static function getInstance()
    {
        static $mInstance = [];
        $class = get_called_class();
        if (!isset($mInstance[$class])) {
            $mInstance[$class] = new $class();
        }
        return $mInstance[$class];
    }

    /**
     * 获取表名
     *
     * @return string
     */
    public function getTableName()
    {
        if (is_null($this->table)) {
            $tmp = get_class($this);
            $this->table = strtolower(substr($tmp, strrpos($tmp, '\\') + 1, -5));
        }
        return $this->table;
    }

    /**
     * 通过某个字段获取单条数据-快捷方法
     *
     * @param mixed $val 值
     * @param string $column 字段名 不传会自动分析表结构获取主键
     * @param string $tableName 表名 不传会自动从当前Model中$table属性获取
     * @param mixed $tablePrefix 表前缀 不传会自动从当前Model中$tablePrefix属性获取再没有则获取配置中配置的前缀
     *
     * @return bool|array
     */
    public function getByColumn($val, $column = null, $tableName = null, $tablePrefix = null)
    {
        is_null($tableName) && $tableName = $this->getTableName();
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;
        is_null($column) && $column = $this->db($this->getDbConf())->getPk($tableName, $tablePrefix);
        return $this->db($this->getDbConf())->table($tableName, $tablePrefix)
            ->where($column, $val)
            ->getOne();
    }

    /**
     * 通过某个字段获取多条数据-快捷方法
     *
     * @param mixed $val 值
     * @param string $column 字段名 不传会自动分析表结构获取主键
     * @param string $tableName 表名 不传会自动从当前Model中$table属性获取
     * @param mixed $tablePrefix 表前缀 不传会自动从当前Model中$tablePrefix属性获取再没有则获取配置中配置的前缀
     *
     * @return bool|array
     */
    public function getMultiByColumn($val, $column = null, $tableName = null, $tablePrefix = null)
    {
        is_null($tableName) && $tableName = $this->getTableName();
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;
        is_null($column) && $column = $this->db($this->getDbConf())->getPk($tableName, $tablePrefix);
        return $this->db($this->getDbConf())->table($tableName, $tablePrefix)
            ->where($column, $val)
            ->select();
    }

    /**
     * 增加一条数据-快捷方法
     *
     * @param array $data 要新增的数据
     * @param string $tableName 表名 不传会自动从当前Model中$table属性获取
     * @param mixed $tablePrefix 表前缀 不传会自动从当前Model中$tablePrefix属性获取再没有则获取配置中配置的前缀
     *
     * @return int
     */
    public function set($data, $tableName = null, $tablePrefix = null)
    {
        is_null($tableName) && $tableName = $this->getTableName();
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;
        return $this->db($this->getDbConf())->set($tableName, $data, $tablePrefix);
    }

    /**
     * 通过字段更新数据-快捷方法
     *
     * @param int $val 字段值
     * @param array $data 更新的数据
     * @param string $column 字段名 不传会自动分析表结构获取主键
     * @param string $tableName 表名 不传会自动从当前Model中$table属性获取
     * @param mixed $tablePrefix 表前缀 不传会自动从当前Model中$tablePrefix属性获取再没有则获取配置中配置的前缀
     *
     * @return bool
     */
    public function updateByColumn($val, $data, $column = null, $tableName = null, $tablePrefix = null)
    {
        is_null($tableName) && $tableName = $this->getTableName();
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;
        is_null($column) && $column = $this->db($this->getDbConf())->getPk($tableName, $tablePrefix);
        return $this->db($this->getDbConf())->where($column, $val)
            ->update($tableName, $data, true, $tablePrefix);
    }

    /**
     * 通过主键删除数据-快捷方法
     *
     * @param mixed $val
     * @param string $column 字段名 不传会自动分析表结构获取主键
     * @param string $tableName 表名 不传会自动从当前Model中$table属性获取
     * @param mixed $tablePrefix 表前缀 不传会自动从当前Model中$tablePrefix属性获取再没有则获取配置中配置的前缀
     *
     * @return bool
     */
    public function delByColumn($val, $column = null, $tableName = null, $tablePrefix = null)
    {
        is_null($tableName) && $tableName = $this->getTableName();
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;
        is_null($column) && $column = $this->db($this->getDbConf())->getPk($tableName, $tablePrefix);
        return $this->db($this->getDbConf())->where($column, $val)
            ->delete($tableName, true, $tablePrefix);
    }

    /**
     * 获取数据的总数
     *
     * @param null $pkField 主键的字段名
     * @param string $tableName 表名 不传会自动从当前Model中$table属性获取
     * @param mixed $tablePrefix 表前缀 不传会自动从当前Model中$tablePrefix属性获取再没有则获取配置中配置的前缀
     *
     * @return mixed
     */
    public function getTotalNums($pkField = null, $tableName = null, $tablePrefix = null)
    {
        is_null($tableName) && $tableName = $this->getTableName();
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;
        is_null($pkField) && $pkField = $this->db($this->getDbConf())->getPk($tableName, $tablePrefix);
        return $this->db($this->getDbConf())->table($tableName, $tablePrefix)->count($pkField);
    }

    /**
     * 获取数据列表
     *
     * @param int $offset 偏移量
     * @param int $limit 返回的条数
     * @param string|array $order 传asc 或 desc 自动取主键 或 ['id'=>'desc', 'status' => 'asc']
     * @param string $tableName 表名 不传会自动从当前Model中$table属性获取
     * @param mixed $tablePrefix 表前缀 不传会自动从当前Model中$tablePrefix属性获取再没有则获取配置中配置的前缀
     *
     * @return array
     */
    public function getList($offset = 0, $limit = 20, $order = 'DESC', $tableName = null, $tablePrefix = null)
    {
        is_null($tableName) && $tableName = $this->getTableName();
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;
        is_array($order) || $order = [$this->db($this->getDbConf())->getPk($tableName, $tablePrefix) => $order];

        $dbInstance = $this->db($this->getDbConf())->table($tableName, $tablePrefix);
        foreach ($order as $key => $val) {
            $dbInstance->orderBy($key, $val);
        }
        return $dbInstance->limit($offset, $limit)
            ->select();
    }

    /**
     * 以分页的方式获取数据列表
     *
     * @param int $limit 每页返回的条数
     * @param string|array $order 传asc 或 desc 自动取主键 或 ['id'=>'desc', 'status' => 'asc']
     * @param string $tableName 表名 不传会自动从当前Model中$table属性获取
     * @param mixed $tablePrefix 表前缀 不传会自动从当前Model中$tablePrefix属性获取再没有则获取配置中配置的前缀
     *
     * @return array
     */
    public function getListByPaginate($limit = 20, $order = 'DESC', $tableName = null, $tablePrefix = null)
    {
        is_null($tableName) && $tableName = $this->getTableName();
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;
        is_array($order) || $order = [$this->db($this->getDbConf())->getPk($tableName, $tablePrefix) => $order];

        $dbInstance = $this->db($this->getDbConf())->table($tableName, $tablePrefix);
        foreach ($order as $key => $val) {
            $dbInstance->orderBy($key, $val);
        }
        return $dbInstance->paginate($limit);
    }

    /**
     * 获取当前Model的数据库配置串
     *
     * @return string
     */
    public function getDbConf()
    {
        return property_exists($this, 'db') ? $this->db : 'default_db';
    }

    /**
     * 自动根据 db属性执行$this->db(xxx)方法; table/tablePrefix属性执行$this->db('xxx')->table('tablename', 'tablePrefix')方法
     *
     * @return \Cml\Db\MySql\Pdo | \Cml\Db\MongoDB\MongoDB | \Cml\Db\Base
     */
    public function mapDbAndTable()
    {
        return $this->db($this->getDbConf())->table($this->getTableName(), $this->tablePrefix);
    }

    /**
     * 当访问model中不存在的方法时直接调用$this->db()的相关方法
     *
     * @param $dbMethod
     * @param $arguments
     *
     * @return \Cml\Db\MySql\Pdo | \Cml\Db\MongoDB\MongoDB | $this
     */
    public function __call($dbMethod, $arguments)
    {
        $res = call_user_func_array([$this->db($this->getDbConf()), $dbMethod], $arguments);
        if ($res instanceof Interfaces\Db) {
            return $this;//不是返回数据直接返回model实例
        } else {
            return $res;
        }
    }

    /**
     * 当访问model中不存在的方法时直接调用相关model中的db()的相关方法
     *
     * @param $dbMethod
     * @param $arguments
     *
     * @return \Cml\Db\MySql\Pdo | \Cml\Db\MongoDB\MongoDB
     */
    public static function __callStatic($dbMethod, $arguments)
    {
        return call_user_func_array([static::getInstance()->db(static::getInstance()->getDbConf()), $dbMethod], $arguments);
    }
}
