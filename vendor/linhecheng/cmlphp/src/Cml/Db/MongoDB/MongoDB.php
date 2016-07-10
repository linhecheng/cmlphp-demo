<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.51beautylife.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 16-3-1 下午18:07
 * @version  2.5
 * cml框架 MongoDB数据库MongoDB驱动类 http://php.net/manual/zh/set.mongodb.php
 * *********************************************************** */
namespace Cml\Db\MongoDB;

use Cml\Cml;
use Cml\Config;
use Cml\Db\Base;
use Cml\Debug;
use Cml\Lang;
use MongoDB\BSON\Regex;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Cursor;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Exception\Exception as MongoDBDriverException;

/**
 * Orm MongoDB数据库MongoDB实现类
 *
 * @see http://php.net/manual/zh/set.mongodb.php
 *
 * @package Cml\Db\MySql
 */
class MongoDB extends Base
{
    /**
     * 最新插入的数据的id
     *
     * @var null
     */
    private $lastInsertId = null;

    /**
     * @var array sql组装
     */
    protected  $sql = array(
        'where' => array(),
        'columns' => array(),
        'limit' => array(0, 5000),
        'orderBy' => array(),
        'groupBy' => '',
        'having' => '',
    );

    /**
     * 标识下个where操作为and 还是 or 默认是and操作
     *
     * @var bool
     */
    private $opIsAnd = true;

    /**
     * or操作中一组条件是否有多个条件
     *
     * @var bool
     */
    private $bracketsIsOpen = false;

    /**
     * 数据库连接串
     *
     * @param $conf
     */
    public function __construct($conf)
    {
        $this->conf = $conf;
        $this->tablePrefix = isset($this->conf['master']['tableprefix']) ? $this->conf['master']['tableprefix'] : '';
    }

    /**
     * 获取当前db所有表名
     *
     * @return array
     */
    public function getTables()
    {
        $tables = array();
        $result = $this->runMongoQuery('system.namespaces');
        foreach ($result as $val) {
            if (strpos($val['name'], '$') === false) {
                $tables[] = substr($val['name'], strpos($val['name'], '.') + 1);
            }
        }
        return $tables;
    }

    /**
     * 获取数据库名
     *
     * @return string
     */
    private function getDbName()
    {
        return $this->conf['master']['dbname'];
    }

    /**
     * 返回从库连接
     *
     * @return Manager
     */
    private function getSlave()
    {
        return $this->rlink;
    }

    /**
     * 返回主库连接
     *
     * @return Manager
     */
    private function getMaster()
    {
        return $this->wlink;
    }

    /**
     * 获取表字段-因为mongodb中collection对字段是没有做强制一制的。这边默认获取第一条数据的所有字段返回
     *
     * @param string $table 表名
     * @param mixed $tablePrefix 表前缀 为null时代表table已经带了前缀
     * @param int $filter 在MongoDB中此选项无效
     *
     * @return mixed
     */
    public function getDbFields($table, $tablePrefix = null, $filter = 0)
    {
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;
        $one = $this->runMongoQuery($tablePrefix . $table, array(), array('limit' => 1));
        return empty($one) ? array() : array_keys($one[0]);
    }

    /**
     * 查询语句条件组装
     *
     * @param string $key eg: 'forum-fid-1-uid-2'
     * @param bool $and 多个条件之间是否为and  true为and false为or
     * @param bool $noCondition 是否为无条件操作  set/delete/update操作的时候 condition为空是正常的不报异常
     * @param bool $noTable 是否可以没有数据表 当delete/update等操作的时候已经执行了table() table为空是正常的
     *
     * @return array eg: array('forum', "`fid` = '1' AND `uid` = '2'")
     */
    protected function parseKey($key, $and = true, $noCondition = false, $noTable = false)
    {
        $keys = explode('-', $key);
        $table = strtolower(array_shift($keys));
        $len = count($keys);
        $condition = array();
        for($i = 0; $i < $len; $i += 2) {
            $val = is_numeric($keys[$i+1]) ? intval($keys[$i+1]) : $keys[$i+1];
            $and ? $condition[$keys[$i]] =  $val : $condition['$or'][][$keys[$i]] = $val;
        }

        (empty($table) && !$noTable) && \Cml\throwException(Lang::get('_DB_PARAM_ERROR_PARSE_KEY_', $key, 'table'));
        (empty($condition) && !$noCondition) && \Cml\throwException(Lang::get('_DB_PARAM_ERROR_PARSE_KEY_', $key, 'condition'));

        return array($table, $condition);
    }

    /**
     * 根据key取出数据
     *
     * @param string $key get('user-uid-123');
     * @param bool $and 多个条件之间是否为and  true为and false为or
     * @param bool|string $useMaster 是否使用主库,mongodb驱动下无效,为了保证一致的操作api保留此选项,此选项为字符串时为表前缀$tablePrefix
     * @param null|string $tablePrefix 表前缀
     *
     * @return array
     */
    public function get($key, $and = true, $useMaster = false, $tablePrefix = null)
    {
        if (is_string($useMaster) && is_null($tablePrefix)) {
            $tablePrefix = $useMaster;
        }

        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;

        list($tableName, $condition) = $this->parseKey($key, $and);

        $filter = array();
        isset($this->sql['limit'][0]) && $filter['skip'] = $this->sql['limit'][0];
        isset($this->sql['limit'][1]) && $filter['limit'] = $this->sql['limit'][1];

        return $this->runMongoQuery($tablePrefix . $tableName, $condition, $filter);
    }


    /**
     * 执行mongoQuery命令
     *
     * @param string $tableName 执行的mongoCollection名称
     * @param array $condition 查询条件
     * @param array $queryOptions 查询的参数
     * @return array
     */
    public function runMongoQuery($tableName, $condition = array(), $queryOptions  = array())
    {
        Cml::$debug && $this->debugLogSql('Query', $tableName, $condition, $queryOptions);

        $this->reset();
        $cursor = $this->getSlave()->executeQuery($this->getDbName() . ".{$tableName}", new Query($condition, $queryOptions));
        $cursor->setTypeMap(['root' => 'array', 'document' => 'array']);
        $result = array();
        foreach ($cursor as $collection) {
            $result[] = $collection;
        }
        return $result;
    }

    /**
     * orm参数重置
     *
     */
    protected  function reset()
    {
        if (!$this->paramsAutoReset) {
            $this->sql['columns'] = array();
            return;
        }

        $this->sql = array(
            'where' => array(),
            'columns' => array(),
            'limit' => array(),
            'orderBy' => array(),
            'groupBy' => '',
            'having' => '',
        );

        $this->table = array(); //操作的表
        $this->join = array(); //是否内联
        $this->leftJoin = array(); //是否左联结
        $this->rightJoin = array(); //是否右联
        $this->whereNeedAddAndOrOr = 0;
        $this->opIsAnd = true;
    }

    /**
     * 执行mongoBulkWrite命令
     *
     * @param string $tableName 执行的mongoCollection名称
     * @param BulkWrite $bulk The MongoDB\Driver\BulkWrite to execute.
     *
     * @return \MongoDB\Driver\WriteResult
     */
    public function runMongoBulkWrite($tableName, BulkWrite $bulk)
    {
        $this->reset();
        $return = false;

        try {
            $return = $this->getMaster()->executeBulkWrite($this->getDbName() . ".{$tableName}", $bulk);
        } catch (BulkWriteException $e) {
            $result = $e->getWriteResult();

            // Check if the write concern could not be fulfilled
            if ($writeConcernError = $result->getWriteConcernError()) {
                \Cml\throwException(sprintf("%s (%d): %s\n",
                    $writeConcernError->getMessage(),
                    $writeConcernError->getCode(),
                    var_export($writeConcernError->getInfo(), true)
                ));
            }

            $errors = array();
            // Check if any write operations did not complete at all
            foreach ($result->getWriteErrors() as $writeError) {
                $errors[] = sprintf("Operation#%d: %s (%d)\n",
                    $writeError->getIndex(),
                    $writeError->getMessage(),
                    $writeError->getCode()
                );
            }
            \Cml\throwException(var_export($errors, true));
        } catch (MongoDBDriverException $e) {
            \Cml\throwException("Other error: %s\n", $e->getMessage());
        }

        return $return;
    }

    /**
     * Debug模式记录查询语句显示到控制台
     *
     * @param string $type 查询的类型
     * @param string $tableName 查询的Collection
     * @param array $condition 条件
     * @param array $options 额外参数
     */
    private function debugLogSql($type = 'Query', $tableName, $condition = array(), $options = array())
    {
        if (Cml::$debug) {
            Debug::addSqlInfo(sprintf(
                "［MongoDB {$type}］ Collection: %s, Condition: %s, Other: %s",
                $this->getDbName() . ".{$tableName}",
                json_encode($condition, PHP_VERSION >= '5.4.0' ? JSON_UNESCAPED_UNICODE : 0),
                json_encode($options, PHP_VERSION >= '5.4.0' ? JSON_UNESCAPED_UNICODE : 0)
            ));
        }
    }

    /**
     * 执行命令
     *
     * @param array $cmd 要执行的Command
     * @param bool $runOnMaster 使用主库还是从库执行 默认使用主库执行
     * @param bool $returnCursor 返回数据还是cursor 默认返回结果数据
     *
     * @return array|Cursor
     */
    public function runMongoCommand($cmd = array(), $runOnMaster = true, $returnCursor = false)
    {
        Cml::$debug && $this->debugLogSql('Command', '', $cmd);

        $this->reset();
        $db = $runOnMaster ? $this->getMaster() : $this->getSlave();
        $cursor = $db->executeCommand($this->getDbName(), new Command($cmd));

        if ($returnCursor) {
            return $cursor;
        } else {
            $cursor->setTypeMap(['root' => 'array', 'document' => 'array']);
            $result = array();
            foreach ($cursor as $collection) {
                $result[] = $collection;
            }
            return $result;
        }
    }

    /**
     * 根据key 新增 一条数据
     *
     * @param string $table
     * @param array $data eg: array('username'=>'admin', 'email'=>'linhechengbush@live.com')
     * @param mixed $tablePrefix 表前缀 不传则获取配置中配置的前缀
     *
     * @return bool|int
     */
    public function set($table, $data, $tablePrefix = null)
    {
        if (is_array($data)) {
            is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;

            $bulk = new BulkWrite();
            $insertId = $bulk->insert($data);
            $result = $this->runMongoBulkWrite($tablePrefix . $table, $bulk);

            Cml::$debug && $this->debugLogSql('BulkWrite INSERT', $tablePrefix . $table, array(), $data);

            if ($result->getInsertedCount() > 0) {
                $this->lastInsertId = sprintf('%s', $insertId);
            }
            return $this->insertId();
        } else {
            return false;
        }
    }

    /**
     * 根据key更新一条数据
     *
     * @param string $key eg 'user-uid-$uid' 如果条件是通用whereXX()、表名是通过table()设定。这边可以直接传$data的数组
     * @param array | null $data eg: array('username'=>'admin', 'email'=>'linhechengbush@live.com')
     * @param bool $and 多个条件之间是否为and  true为and false为or
     * @param mixed $tablePrefix 表前缀 不传则获取配置中配置的前缀
     *
     * @return boolean
     */
    public function update($key, $data = null, $and = true, $tablePrefix = null)
    {
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;
        $tableName = $condition = '';

        if (is_array($data)) {
            list($tableName, $condition) = $this->parseKey($key, $and, true, true);
        } else {
            $data = $key;
        }

        $tableName = empty($tableName) ? $this->getRealTableName(key($this->table)) : $tablePrefix . $tableName;
        empty($tableName) && \Cml\throwException(Lang::get('_PARSE_SQL_ERROR_NO_TABLE_', 'update'));
        $condition += $this->sql['where'];
        empty($condition) && \Cml\throwException(Lang::get('_PARSE_SQL_ERROR_NO_CONDITION_', 'update'));

        $bulk = new BulkWrite();
        $bulk->update($condition, array('$set' => $data), array('multi' => true));
        $result = $this->runMongoBulkWrite($tableName, $bulk);

        Cml::$debug && $this->debugLogSql('BulkWrite UPDATE', $tableName, $condition, $data);

        return $result->getModifiedCount();
    }

    /**
     * 根据key值删除数据
     *
     * @param string $key eg: 'user-uid-$uid'
     * @param bool $and 多个条件之间是否为and  true为and false为or
     * @param mixed $tablePrefix 表前缀 不传则获取配置中配置的前缀
     *
     * @return boolean
     */
    public function delete($key = '', $and = true, $tablePrefix = null)
    {
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;
        $tableName = $condition = '';

        empty($key) || list($tableName, $condition) = $this->parseKey($key, $and, true, true);

        $tableName = empty($tableName) ? $this->getRealTableName(key($this->table)) : $tablePrefix . $tableName;
        empty($tableName) && \Cml\throwException(Lang::get('_PARSE_SQL_ERROR_NO_TABLE_', 'delete'));
        $condition += $this->sql['where'];
        empty($condition) && \Cml\throwException(Lang::get('_PARSE_SQL_ERROR_NO_CONDITION_', 'delete'));

        $bulk = new BulkWrite();
        $bulk->delete($condition);
        $result = $this->runMongoBulkWrite($tableName, $bulk);

        Cml::$debug && $this->debugLogSql('BulkWrite DELETE', $tableName, $condition);

        return $result->getDeletedCount();
    }

    /**
     * 获取处理后的表名
     *
     * @param $table
     * @return string
     */
    private function getRealTableName($table)
    {
        return substr($table, strpos($table, '_') + 1);
    }

    /**
     * 清空集合 这个操作太危险所以直接屏蔽了
     *
     * @param string $tableName 要清空的表名
     *
     * @return bool
     */
    public function truncate($tableName)
    {
        return false;
    }

    /**
     * 获取表主键 mongo直接返回 '_id'
     *
     * @param string $table 要获取主键的表名
     * @param string $tablePrefix 表前缀
     *
     * @return string || false
     */
    public function getPk($table, $tablePrefix = null)
    {
        return '_id';
    }

    /**
     * where 语句组装工厂
     *
     * @param string $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
     * @param array|int|string $value 值
     * @param string $operator 操作符
     * @throws \Exception
     */
    public function conditionFactory($column, $value, $operator = '=')
    {
        $currentOrIndex = isset($this->sql['where']['$or']) ? count($this->sql['where']['$or']) - 1 : 0;

        if ($this->opIsAnd) {
            isset($this->sql['where'][$column][$operator]) && \Cml\throwException('Mongodb Where Op key Is Exists['.$column.$operator.']');
        } else if ($this->bracketsIsOpen) {
            isset($this->sql['where']['$or'][$currentOrIndex][$column][$operator]) && \Cml\throwException('Mongodb Where Op key Is Exists['.$column.$operator.']');
        }

        switch ($operator) {
            case 'IN':
                // no break
            case 'NOT IN':
                empty($value) && $value = array(0);
                //这边可直接跳过不组装sql，但是为了给用户提示无条件 便于调试还是加上where field in(0)
                if ($this->opIsAnd) {
                    $this->sql['where'][$column][$operator == 'IN' ? '$in' : '$nin'] = $value;
                } else if ($this->bracketsIsOpen) {
                    $this->sql['where']['$or'][$currentOrIndex][$column][$operator == 'IN' ? '$in' : '$nin'] = $value ;
                } else {
                    $this->sql['where']['$or'][][$column] = $operator == 'IN' ? array('$in' => $value) : array('$nin' => $value);
                }
                break;
            case 'BETWEEN':
                if ($this->opIsAnd) {
                    $this->sql['where'][$column]['$gt'] = $value[0];
                    $this->sql['where'][$column]['$lt'] = $value[1];
                } else if ($this->bracketsIsOpen) {
                    $this->sql['where']['$or'][$currentOrIndex][$column]['$gt'] = $value[0];
                    $this->sql['where']['$or'][$currentOrIndex][$column]['$lt'] = $value[1];
                } else {
                    $this->sql['where']['$or'][][$column] = array('$gt' => $value[0], '$lt' => $value[1]);
                }
                break;
            case 'NOT BETWEEN':
                if ($this->opIsAnd) {
                    $this->sql['where'][$column]['$lt'] = $value[0];
                    $this->sql['where'][$column]['$gt'] = $value[1];
                } else if ($this->bracketsIsOpen) {
                    $this->sql['where']['$or'][$currentOrIndex][$column]['$lt'] = $value[0];
                    $this->sql['where']['$or'][$currentOrIndex][$column]['$gt'] = $value[1];
                } else {
                    $this->sql['where']['$or'][][$column] = array('$lt' => $value[0], '$gt' => $value[1]);
                }
                break;
            case 'IS NULL':
                if ($this->opIsAnd) {
                    $this->sql['where'][$column]['$in'] = array(null);
                    $this->sql['where'][$column]['$exists'] = true;
                } else if ($this->bracketsIsOpen) {
                    $this->sql['where']['$or'][$currentOrIndex][$column]['$in'] = array(null);
                    $this->sql['where']['$or'][$currentOrIndex][$column]['$exists'] = true;
                } else {
                    $this->sql['where']['$or'][][$column] = array('$in' => array(null), '$exists' => true);
                }
                break;
            case 'IS NOT NULL':
                if ($this->opIsAnd) {
                    $this->sql['where'][$column]['$ne'] = null;
                    $this->sql['where'][$column]['$exists'] = true;
                } else if ($this->bracketsIsOpen) {
                    $this->sql['where']['$or'][$currentOrIndex][$column]['$ne'] = null;
                    $this->sql['where']['$or'][$currentOrIndex][$column]['$exists'] = true;
                } else {
                    $this->sql['where']['$or'][][$column] = array('$ne' => null, '$exists' => true);
                }
                break;
            case '>':
                //no break;
            case '<':
                if ($this->opIsAnd) {
                    $this->sql['where'][$column][$operator == '>' ? '$gt' : '$lt'] = $value;
                } else if ($this->bracketsIsOpen) {
                    $this->sql['where']['$or'][$currentOrIndex][$column][$operator == '>' ? '$gt' : '$lt'] = $value;
                } else {
                    $this->sql['where']['$or'][][$column] = $operator == '>' ? array('$gt' => $value) : array('$lt' => $value);
                }
                break;
            case '>=':
                //no break;
            case '<=':
                if ($this->opIsAnd) {
                    $this->sql['where'][$column][$operator == '>=' ? '$gte' : '$lte'] = $value;
                } else if ($this->bracketsIsOpen) {
                    $this->sql['where']['$or'][$currentOrIndex][$column][$operator == '>=' ? '$gte' : '$lte'] = $value;
                } else {
                    $this->sql['where']['$or'][][$column] = $operator == '>=' ? array('$gte' => $value) : array('$lte' => $value);
                }
                break;
            case 'NOT LIKE':
                if ($this->opIsAnd) {
                    $this->sql['where'][$column]['$not'] = new Regex($value, 'i');
                } else if ($this->bracketsIsOpen) {
                    $this->sql['where']['$or'][$currentOrIndex][$column]['$not'] = new Regex($value, 'i');
                } else {
                    $this->sql['where']['$or'][][$column] = ['$not' => new Regex($value, 'i')];
                }
                break;
            case 'LIKE':
                //no break;
            case 'REGEXP':
                if ($this->opIsAnd) {
                    $this->sql['where'][$column]['$regex'] = $value;
                    $this->sql['where'][$column]['$options'] = '$i';
                } else if ($this->bracketsIsOpen) {
                    $this->sql['where']['$or'][$currentOrIndex][$column]['$regex'] = $value;
                    $this->sql['where']['$or'][$currentOrIndex][$column]['$options'] = '$i';
                } else {
                    $this->sql['where']['$or'][][$column] = ['$regex' => $value, '$options' => '$i'];
                }
                break;
            case '!=':
                if ($this->opIsAnd) {
                    $this->sql['where'][$column]['$ne'] = $value;
                } else if ($this->bracketsIsOpen) {
                    $this->sql['where']['$or'][$currentOrIndex][$column]['$ne'] = $value;
                } else {
                    $this->sql['where']['$or'][][$column] = array('$ne' => $value);
                }
                break;
            case '=':
                if ($this->opIsAnd) {
                    $this->sql['where'][$column] = $value;
                } else if ($this->bracketsIsOpen) {
                    $this->sql['where']['$or'][$currentOrIndex][$column] = $value;
                } else {
                    $this->sql['where']['$or'][][$column] = $value;
                }
                break;
        }
    }

    /**
     * where条件组装 LIKE
     *
     * @param string $column  如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
     * @param bool $leftBlur 是否开始左模糊匹配
     * @param string |int $value
     * @param bool $rightBlur 是否开始右模糊匹配
     *
     * @return $this
     */
    public function whereLike($column, $leftBlur = false, $value, $rightBlur = false)
    {
        $this->conditionFactory(
            $column,
            ($leftBlur ? '' : '^').preg_quote($this->filterLike($value)).($rightBlur ? '' : '$'),
            'LIKE'
        );
        return $this;
    }

    /**
     * where条件组装 LIKE
     *
     * @param string $column  如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
     * @param bool $leftBlur 是否开始左模糊匹配
     * @param string |int $value
     * @param bool $rightBlur 是否开始右模糊匹配
     *
     * @return $this
     */
    public function whereNotLike($column, $leftBlur = false, $value, $rightBlur = false)
    {
        $this->conditionFactory(
            $column,
            ($leftBlur ? '' : '^').preg_quote($this->filterLike($value)).($rightBlur ? '' : '$'),
            'NOT LIKE'
        );
        return $this;
    }

    /**
     * 选择列
     *
     * @param string|array $columns 默认选取所有 array('id, 'name') 选取id,name两列
     *
     * @return $this
     */
    public function columns($columns = '*')
    {
        if (false === is_array($columns) && $columns != '*') {
            $columns = func_get_args();
        }
        foreach($columns as $column) {
            $this->sql['columns'][$column] = 1;
        }
        return $this;
    }

    /**
     * 排序
     *
     * @param string $column 要排序的字段
     * @param string $order 方向,默认为正序
     *
     * @return $this
     */
    public function orderBy($column, $order = 'ASC')
    {
        $this->sql['orderBy'][$column] = strtolower($order) === 'ASC' ? 1 : -1;
        return $this;
    }

    /**
     * 分组 MongoDB中的聚合方式跟 sql不一样这个操作屏蔽。如果要使用聚合直接使用MongoDB Command
     *
     * @param string $column 要设置分组的字段名
     *
     * @return $this
     */
    public function groupBy($column)
    {
        return false;
    }

    /**
     * having语句 MongoDB不支持此命令
     *
     * @param string $column 字段名
     * @param string $operator 操作符
     * @param string $value 值
     *
     * @return $this
     */
    public function having($column, $operator = '=', $value)
    {
        return false;
    }

    /**
     * join内联结 MongoDB不支持此命令
     *
     * @param string|array $table 表名 要取别名时使用 array(不带前缀表名 => 别名)
     * @param string $on 联结的条件 如：'c.cid = a.cid'
     * @param mixed $tablePrefix 表前缀
     *
     * @return $this
     */
    public function join($table, $on, $tablePrefix = null)
    {
        return false;
    }

    /**
     * leftJoin左联结 MongoDB不支持此命令
     *
     * @param string|array $table 表名 要取别名时使用 array(不带前缀表名 => 别名)
     * @param string $on 联结的条件 如：'c.cid = a.cid'
     * @param mixed $tablePrefix 表前缀
     *
     * @return $this
     */
    public function leftJoin($table, $on, $tablePrefix = null)
    {
        return false;
    }

    /**
     * rightJoin右联结 MongoDB不支持此命令
     *
     * @param string|array $table 表名 要取别名时使用 array(不带前缀表名 => 别名)
     * @param string $on 联结的条件 如：'c.cid = a.cid'
     * @param mixed $tablePrefix 表前缀
     *
     * @return $this
     */
    public function rightJoin($table, $on, $tablePrefix = null)
    {
        return false;
    }

    /**
     * union联结 MongoDB不支持此命令
     *
     * @param string|array $sql 要union的sql
     * @param bool $all 是否为union all
     *
     * @return $this
     */
    public function union($sql, $all = false)
    {
        return false;
    }

    /**
     * 设置后面的where以and连接
     *
     * @return $this
     */
    public function _and()
    {
        $this->opIsAnd = true;
        return $this;
    }

    /**
     * 设置后面的where以or连接
     *
     * @return $this
     */
    public function _or()
    {
        $this->opIsAnd = false;
        return $this;
    }

    /**
     * 在$or操作中让一组条件支持多个条件
     *
     * @return $this
     */
    public function lBrackets()
    {
        $this->bracketsIsOpen = true;
        return $this;
    }

    /**
     * $or操作中关闭一组条件支持多个条件，启动另外一组条件
     *
     * @return $this
     */
    public function rBrackets()
    {
        $this->bracketsIsOpen = false;
        return $this;
    }

    /**
     * LIMIT
     *
     * @param int $offset 偏移量
     * @param int $limit 返回的条数
     *
     * @return $this
     */
    public function limit($offset = 0, $limit = 10)
    {
        ($limit < 1 || $limit > 5000) && $limit = 100;
        $this->sql['limit'] = array($offset, $limit);
        return $this;
    }

    /**
     * 获取count(字段名或*)的结果
     *
     * @param string $field Mongo中此选项无效
     * @param bool $isMulti Mongo中此选项无效
     *
     * @return mixed
     */
    public function count($field = '*', $isMulti = false)
    {
        $cmd = array(
            'count' => $this->getRealTableName(key($this->table)),
            'query' => $this->sql['where']
        );

        $count = $this->runMongoCommand($cmd);
        return intval($count[0]['n']);
    }

    /**
     * MongoDb的distinct封装
     *
     * @param string $field 指定不重复的字段值
     *
     * @return mixed
     */
    public function mongoDbDistinct($field = '')
    {
        $cmd = array(
            'distinct' => $this->getRealTableName(key($this->table)),
            'key' => $field,
            'query' => $this->sql['where']
        );

        $data = $this->runMongoCommand($cmd);
        return $data[0]['values'];
    }

    /**
     * MongoDb的aggregate封装
     *
     * @param array $pipeline List of pipeline operations
     * @param array $options  Command options
     *
     * @return mixed
     */
    public function mongoDbAggregate($pipeline = array(), $options = array())
    {
        $cmd = $options + array(
                'aggregate' => $this->getRealTableName(key($this->table)),
                'pipeline' => $pipeline
            );

        $data = $this->runMongoCommand($cmd);
        return $data[0]['result'];
    }

    /**
     * 获取自增id-需要先初始化数据 如:
     * db.mongoinckeycol.insert({id:0, 'table' : 'post'}) 即初始化帖子表(post)自增初始值为0
     *
     * @param string $collection 存储自增的collection名
     *
     * @param string $table 表的名称
     *
     * @return int
     */
    public function getMongoDbAutoIncKey($collection = 'mongoinckeycol', $table = 'post')
    {
        $res = $this->runMongoCommand(array(
            'findandmodify' => $collection,
            'update' => array(
                '$inc' => array('id' => 1)
            ),
            'query' => array(
                'table' => $table
            ),
            'new' => true
        ));
        return intval($res[0]['value']['id']);
    }

    /**
     * 获取多条数据
     *
     * @param int $offset 偏移量
     * @param int $limit 返回的条数
     *
     * @return array
     */
    public function select($offset = null, $limit = null)
    {
        is_null($offset) || $this->limit($offset, $limit);

        $filter = array();
        count($this->sql['orderBy']) > 0 && $filter['sort'] = $this->sql['orderBy'];
        count($this->sql['columns']) > 0 && $filter['projection'] = $this->sql['columns'];
        isset($this->sql['limit'][0]) && $filter['skip'] = $this->sql['limit'][0];
        isset($this->sql['limit'][1]) && $filter['limit'] = $this->sql['limit'][1];

        return $this->runMongoQuery(
            $this->getRealTableName(key($this->table)),
            $this->sql['where'],
            $filter
        );
    }

    /**
     * 返回INSERT，UPDATE 或 DELETE 查询所影响的记录行数
     *
     * @param \MongoDB\Driver\WriteResult $handle
     * @param int $type 执行的类型1:insert、2:update、3:delete
     *
     * @return int
     */
    public function affectedRows($handle, $type)
    {
        switch ($type) {
            case 1:
                return $handle->getInsertedCount();
                break;
            case 2:
                return $handle->getModifiedCount();
                break;
            case 3:
                return $handle->getDeletedCount();
                break;
            default:
                return false;
        }
    }

    /**
     * 获取上一INSERT的主键值
     *
     * @param mixed $link MongoDdb中此选项无效
     *
     * @return int
     */
    public function insertId($link = null)
    {
        return $this->lastInsertId;
    }

    /**
     * 魔术方法 自动获取相应db实例
     *
     * @param string $db 要连接的数据库类型
     *
     * @return  resource MongoDB 连接标识
     */
    public function __get($db)
    {
        if ($db == 'rlink') {
            //如果没有指定从数据库，则使用 master
            if (!isset($this->conf['slaves']) || empty($this->conf['slaves'])) {
                $this->rlink = $this->wlink;
                return $this->rlink;
            }

            $n = mt_rand(0, count($this->conf['slaves']) - 1);
            $conf = $this->conf['slaves'][$n];
            $this->rlink = $this->connect(
                $conf['host'],
                $conf['username'],
                $conf['password'],
                $conf['dbname'],
                isset($conf['replicaSet']) ? $conf['replicaSet'] : ''
            );
            return $this->rlink;
        } elseif ($db == 'wlink') {
            $conf = $this->conf['master'];
            $this->wlink = $this->connect(
                $conf['host'],
                $conf['username'],
                $conf['password'],
                $conf['dbname'],
                isset($conf['replicaSet']) ? $conf['replicaSet'] : ''
            );
            return $this->wlink;
        }
        return false;
    }

    /**
     * Db连接
     *
     * @param string $host 数据库host
     * @param string $username 数据库用户名
     * @param string $password 数据库密码
     * @param string $dbName 数据库名
     * @param string $replicaSet replicaSet名称
     * @param string $engine 无用
     * @param bool $pConnect 无用
     *
     * @return mixed
     */
    public function connect($host, $username, $password, $dbName, $replicaSet = '', $engine = '', $pConnect = false)
    {
        $authString = "";
        if ($username && $password) {
            $authString = "{$username}:{$password}@";
        }

        $replicaSet && $replicaSet = '?replicaSet='.$replicaSet;
        $dsn = "mongodb://{$authString}{$host}/{$dbName}{$replicaSet}";

        return new Manager($dsn);
    }

    /**
     * 指定字段的值+1
     *
     * @param string $key 操作的key user-id-1
     * @param int $val
     * @param string $field 要改变的字段
     * @param mixed $tablePrefix 表前缀 不传则获取配置中配置的前缀
     *
     * @return bool
     */
    public function increment($key, $val = 1, $field = null, $tablePrefix = null)
    {
        list($tableName, $condition) = $this->parseKey($key, true);
        if (is_null($field) || empty($tableName) || empty($condition)) {
            return false;
        }
        $val = abs(intval($val));
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;
        $tableName = $tablePrefix.$tableName;

        $bulk = new BulkWrite();
        $bulk->update($condition, array('$inc' => array($field => $val)), array('multi' => true));
        $result = $this->runMongoBulkWrite($tableName, $bulk);

        Cml::$debug && $this->debugLogSql('BulkWrite INC', $tableName, $condition, array('$inc' => array($field => $val)));

        return $result->getModifiedCount();
    }

    /**
     * 指定字段的值-1
     *
     * @param string $key 操作的key user-id-1
     * @param int $val
     * @param string $field 要改变的字段
     * @param mixed $tablePrefix 表前缀 不传则获取配置中配置的前缀
     *
     * @return bool
     */
    public function decrement($key, $val = 1, $field = null, $tablePrefix = null)
    {
        list($tableName, $condition) = $this->parseKey($key, true);
        if (is_null($field) || empty($tableName) || empty($condition)) {
            return false;
        }
        $val = abs(intval($val));

        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;
        $tableName = $tablePrefix.$tableName;

        $bulk = new BulkWrite();
        $bulk->update($condition, array('$inc' => array($field => -$val)), array('multi' => true));
        $result = $this->runMongoBulkWrite($tableName, $bulk);

        Cml::$debug && $this->debugLogSql('BulkWrite DEC', $tableName, $condition, array('$inc' => array($field => -$val)));

        return $result->getModifiedCount();
    }

    /**
     *析构函数
     *
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * 关闭连接
     *
     */
    public function close()
    {
        if (!empty($this->wlink)) {
            Config::get('session_user') || $this->wlink = null; //开启会话自定义保存时，不关闭防止会话保存失败
        }
    }

    /**
     *获取mysql 版本
     *
     *@param \PDO $link
     *
     *@return string
     */
    public function version($link = null)
    {
        $cursor = $this->getMaster()->executeCommand(
            $this->getDbName(),
            new Command(['buildInfo' => 1])
        );

        $cursor->setTypeMap(['root' => 'array', 'document' => 'array']);
        $info = current($cursor->toArray());
        return $info['version'];
    }

    /**
     * 开启事务-MongoDb不支持
     *
     * @return bool
     */
    public function  startTransAction()
    {
        return false;
    }

    /**
     * 提交事务-MongoDb不支持
     *
     * @return bool
     */
    public function commit()
    {
        return false;
    }

    /**
     * 设置一个事务保存点-MongoDb不支持
     *
     * @param string $pointName
     *
     * @return bool
     */
    public function savePoint($pointName)
    {
        return false;
    }

    /**
     * 回滚事务-MongoDb不支持
     *
     * @param bool $rollBackTo 是否为还原到某个保存点
     *
     * @return bool
     */
    public function rollBack($rollBackTo = false)
    {
        return false;
    }

    /**
     * 调用存储过程-MongoDb不支持
     *
     * @param string $procedureName 要调用的存储过程名称
     * @param array $bindParams 绑定的参数
     * @param bool|true $isSelect 是否为返回数据集的语句
     *
     * @return array|int
     */
    public function callProcedure($procedureName = '', $bindParams = array(), $isSelect = true)
    {
        return false;
    }
}