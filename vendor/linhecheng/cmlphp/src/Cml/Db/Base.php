<?php
/* * *********************************************************
 * [cmlphp] (C)2012 - 3000 http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-8 下午3:07
 * @version  @see \Cml\Cml::VERSION
 * cmlphp框架 Db 数据库抽象基类
 * *********************************************************** */
namespace Cml\Db;

use Cml\Cml;
use Cml\Config;
use Cml\Http\Input;
use Cml\Interfaces\Db;
use Cml\Lang;
use Cml\Model;
use Cml\Route;

/**
 * Orm 数据库抽象基类
 *
 * @package Cml\Db
 */
abstract class Base implements Db
{
    /**
     * where操作需要加上and/or
     * 0 : 初始化两个都不加
     * 1 : 要加and
     * 2： 要加 or
     *
     * @var int
     */
    protected $whereNeedAddAndOrOr = 0;

    /**
     * 执行sql时绑定的参数
     *
     * @var array
     */
    protected $bindParams = [];

    /**
     * 配置信息
     *
     * @var array
     */
    protected $conf;

    /**
     * 表前缀方便外部读取
     *
     * @var string
     */
    public $tablePrefix;

    /**
     * sql组装
     *
     * @var array
     */
    protected $sql = [
        'where' => '',
        'columns' => '',
        'limit' => '',
        'orderBy' => '',
        'groupBy' => '',
        'having' => '',
    ];

    /**
     * 操作的表
     *
     * @var array
     */
    protected $table = [];

    /**
     * 是否内联 [表名 => 条件]
     *
     * @var array
     */
    protected $join = [];

    /**
     * 是否左联结 写法同内联
     *
     * @var array
     */
    protected $leftJoin = [];

    /**
     * 是否右联 写法同内联
     *
     * @var array
     */
    protected $rightJoin = [];

    /**
     * UNION 写法同内联
     *
     * @var string
     */
    protected $union = '';

    /**
     * orm参数是否自动重置
     *
     * @var bool
     */
    protected $paramsAutoReset = true;


    /**
     * 定义操作的表
     *
     * @param string|array $table 表名 要取别名时使用 [不带前缀表名 => 别名]
     * @param mixed $tablePrefix 表前缀 不传则获取配置中配置的前缀
     *
     * @return $this
     */
    public function table($table = '', $tablePrefix = null)
    {
        $hasAlias = is_array($table) ? true : false;
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;
        $tableName = $tablePrefix . ($hasAlias ? key($table) : $table);

        $this->table[count($this->table) . '_' . $tableName] = $hasAlias ? current($table) : null;
        return $this;
    }

    /**
     * 魔术方法 自动获取相应db实例
     *
     * @param string $db 要连接的数据库类型
     *
     * @return  resource|false 数据库 连接标识
     */
    public function __get($db)
    {
        if ($db == 'rlink') {
            //如果没有指定从数据库，则使用 master
            if (empty($this->conf['slaves'])) {
                $this->rlink = $this->wlink;
                return $this->rlink;
            }

            $n = mt_rand(0, count($this->conf['slaves']) - 1);
            $conf = $this->conf['slaves'][$n];
            empty($conf['engine']) && $conf['engine'] = '';
            $this->rlink = $this->connect(
                $conf['host'],
                $conf['username'],
                $conf['password'],
                $conf['dbname'],
                $conf['charset'],
                $conf['engine'],
                $conf['pconnect']
            );
            return $this->rlink;
        } elseif ($db == 'wlink') {
            $conf = $this->conf['master'];
            empty($conf['engine']) && $conf['engine'] = '';
            $this->wlink = $this->connect(
                $conf['host'],
                $conf['username'],
                $conf['password'],
                $conf['dbname'],
                $conf['charset'],
                $conf['engine'],
                $conf['pconnect']
            );
            return $this->wlink;
        }
        return false;
    }

    /**
     * 分页获取数据
     *
     * @param int $limit 每页返回的条数
     * @param bool $useMaster 是否使用主库 默认读取从库
     *
     * @return array
     */
    public function paginate($limit, $useMaster = false)
    {
        $page = Input::requestInt(Config::get('var_page'), 1);
        $page < 1 && $page = 1;
        return call_user_func_array([$this, 'select'], [($page - 1) * $limit, $limit, $useMaster]);
    }

    /**
     * 获取表主键
     *
     * @param string $table 要获取主键的表名
     * @param string $tablePrefix 表前缀
     *
     * @return string || false
     */
    public function getPk($table, $tablePrefix = null)
    {
        $rows = $this->getDbFields($table, is_null($tablePrefix) ? $this->tablePrefix : $tablePrefix);
        foreach ($rows as $val) {
            if ($val['primary']) {
                return $val['name'];
            }
        }
        return false;
    }

    /**
     * 获取多条数据
     *
     * @param bool $useMaster 是否使用主库 默认读取从库
     *
     * @return array | bool
     */
    public function getOne($useMaster = false)
    {
        $result = $this->select(0, 1, $useMaster);
        if (isset($result[0])) {
            return $result[0];
        } else {
            return false;
        }
    }

    /**
     * where条件组装 相等
     *
     * @param string|array $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名) 当$column为数组时 批量设置
     * @param string |int $value 当$column为数组时  此时$value为false时条件为or 否则为and
     *
     * @return $this
     */
    public function where($column, $value = '')
    {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->whereNeedAddAndOrOr > 0 && ($value === false ? $this->_or() : $this->_and());
                $this->conditionFactory($key, $val, '=');
            }
        } else {
            $this->conditionFactory($column, $value, '=');
        }
        return $this;
    }

    /**
     * where条件组装 不等
     *
     * @param string $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
     * @param string |int $value
     *
     * @return $this
     */
    public function whereNot($column, $value)
    {
        $this->conditionFactory($column, $value, '!=');
        return $this;
    }

    /**
     * where条件组装 大于
     *
     * @param string $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
     * @param string |int $value
     *
     * @return $this
     */
    public function whereGt($column, $value)
    {
        $this->conditionFactory($column, $value, '>');
        return $this;
    }

    /**
     * where条件组装 小于
     *
     * @param string $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
     * @param string |int $value
     *
     * @return $this
     */
    public function whereLt($column, $value)
    {
        $this->conditionFactory($column, $value, '<');
        return $this;
    }

    /**
     * where条件组装 大于等于
     *
     * @param string $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
     * @param string |int $value
     *
     * @return $this
     */
    public function whereGte($column, $value)
    {
        $this->conditionFactory($column, $value, '>=');
        return $this;
    }

    /**
     * where条件组装 小于等于
     *
     * @param string $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
     * @param string |int $value
     *
     * @return $this
     */
    public function whereLte($column, $value)
    {
        $this->conditionFactory($column, $value, '<=');
        return $this;
    }

    /**
     * where条件组装 in
     *
     * @param string $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
     * @param array $value
     *
     * @return $this
     */
    public function whereIn($column, $value)
    {
        $this->conditionFactory($column, $value, 'IN');
        return $this;
    }

    /**
     * where条件组装 not in
     *
     * @param string $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
     * @param array $value [1,2,3]
     *
     * @return $this
     */
    public function whereNotIn($column, $value)
    {
        $this->conditionFactory($column, $value, 'NOT IN');
        return $this;
    }

    /**
     * where条件组装 REGEXP
     *
     * @param string $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
     * @param string |int $value
     *
     * @return $this
     */
    public function whereRegExp($column, $value)
    {
        $this->conditionFactory($column, $value, 'REGEXP');
        return $this;
    }

    /**
     * where条件组装 LIKE
     *
     * @param string $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
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
            ($leftBlur ? '%' : '') . $this->filterLike($value) . ($rightBlur ? '%' : ''),
            'LIKE'
        );
        return $this;
    }

    /**
     * where条件组装 LIKE
     *
     * @param string $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
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
            ($leftBlur ? '%' : '') . $this->filterLike($value) . ($rightBlur ? '%' : ''),
            'NOT LIKE'
        );
        return $this;
    }

    /**
     * where 用户输入过滤
     *
     * @param string $val
     *
     * @return string
     */
    protected function filterLike($val)
    {
        return str_replace(['_', '%'], ['\_', '\%'], $val);
    }

    /**
     * where条件组装 BETWEEN
     *
     * @param string $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
     * @param string |int | array $value
     * @param string |int | null $value2
     *
     * @return $this
     */
    public function whereBetween($column, $value, $value2 = null)
    {
        if (is_null($value2)) {
            if (!is_array($value)) {
                throw new \InvalidArgumentException(Lang::get('_DB_PARAM_ERROR_WHERE_BETWEEN_'));
            }
            $val = $value;
        } else {
            $val = [$value, $value2];
        }
        $this->conditionFactory($column, $val, 'BETWEEN');
        return $this;
    }

    /**
     * where条件组装 NOT BETWEEN
     *
     * @param string $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
     * @param string |int | array $value
     * @param string |int | null $value2
     *
     * @return $this
     */
    public function whereNotBetween($column, $value, $value2 = null)
    {
        if (is_null($value2)) {
            if (!is_array($value)) {
                throw new \InvalidArgumentException(Lang::get('_DB_PARAM_ERROR_WHERE_BETWEEN_'));
            }
            $val = $value;
        } else {
            $val = [$value, $value2];
        }
        $this->conditionFactory($column, $val, 'NOT BETWEEN');
        return $this;
    }

    /**
     * where条件组装 IS NULL
     *
     * @param string $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
     *
     * @return $this
     */
    public function whereNull($column)
    {
        $this->conditionFactory($column, '', 'IS NULL');
        return $this;
    }

    /**
     * where条件组装 IS NOT NULL
     *
     * @param string $column 如 id  user.id (这边的user为表别名如表pre_user as user 这边用user而非带前缀的原表名)
     *
     * @return $this
     */
    public function whereNotNull($column)
    {
        $this->conditionFactory($column, '', 'IS NOT NULL');
        return $this;
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
        if ($this->sql['where'] == '') $this->sql['where'] = 'WHERE ';

        if ($this->whereNeedAddAndOrOr === 1) {
            $this->sql['where'] .= ' AND ';
        } else if ($this->whereNeedAddAndOrOr === 2) {
            $this->sql['where'] .= ' OR ';
        }

        //下一次where操作默认加上AND
        $this->whereNeedAddAndOrOr = 1;

        if ($operator == 'IN' || $operator == 'NOT IN') {
            empty($value) && $value = [0];
            //这边可直接跳过不组装sql，但是为了给用户提示无条件 便于调试还是加上where field in(0)
            $inValue = '(';
            foreach ($value as $val) {
                $inValue .= '%s ,';
                $this->bindParams[] = $val;
            }
            $this->sql['where'] .= "{$column} {$operator} " . rtrim($inValue, ',') . ') ';
        } elseif ($operator == 'BETWEEN' || $operator == 'NOT BETWEEN') {
            $betweenValue = '%s AND %s ';
            $this->bindParams[] = $value[0];
            $this->bindParams[] = $value[1];
            $this->sql['where'] .= "{$column} {$operator} {$betweenValue}";
        } else if ($operator == 'IS NULL' || $operator == 'IS NOT NULL') {
            $this->sql['where'] .= "{$column} {$operator}";
        } else {
            $this->bindParams[] = $value;
            $value = '%s';
            $this->sql['where'] .= "{$column} {$operator} {$value} ";
        }
    }

    /**
     * 增加 and条件操作符
     *
     * @return $this
     */
    public function _and()
    {
        $this->whereNeedAddAndOrOr = 1;
        return $this;
    }

    /**
     * 增加or条件操作符
     *
     * @return $this
     */
    public function _or()
    {
        $this->whereNeedAddAndOrOr = 2;
        return $this;
    }

    /**
     * where条件增加左括号
     *
     * @return $this
     */
    public function lBrackets()
    {
        if ($this->sql['where'] == '') {
            $this->sql['where'] = 'WHERE ';
        } else {
            if ($this->whereNeedAddAndOrOr === 1) {
                $this->sql['where'] .= ' AND ';
            } else if ($this->whereNeedAddAndOrOr === 2) {
                $this->sql['where'] .= ' OR ';
            }
        }
        $this->sql['where'] .= ' (';
        //移除下一次where操作默认加上AND
        $this->whereNeedAddAndOrOr = 0;
        return $this;
    }

    /**
     * where条件增加右括号
     *
     * @return $this
     */
    public function rBrackets()
    {
        $this->sql['where'] .= ') ';
        return $this;
    }

    /**
     * 选择列
     *
     * @param string|array $columns 默认选取所有 ['id, 'name']
     * 选取id,name两列，['article.id' => 'aid', 'article.title' =>　'article_title'] 别名
     *
     * @return $this
     */
    public function columns($columns = '*')
    {
        $result = '';
        if (is_array($columns)) {
            foreach ($columns as $key => $val) {
                $result .= ($result == '' ? '' : ', ') . (is_int($key) ? $val : ($key . " AS `{$val}`"));
            }
        } else {
            $args = func_get_args();
            while ($arg = current($args)) {
                $result .= ($result == '' ? '' : ', ') . $arg;
                next($args);
            }
        }
        $this->sql['columns'] == '' || ($this->sql['columns'] .= ' ,');
        $this->sql['columns'] .= $result;
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
        $offset = intval($offset);
        $limit = intval($limit);
        $offset < 0 && $offset = 0;
        ($limit < 1 || $limit > 5000) && $limit = 100;
        $this->sql['limit'] = "LIMIT {$offset}, {$limit}";
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
        if ($this->sql['orderBy'] == '') {
            $this->sql['orderBy'] = "ORDER BY {$column} {$order} ";
        } else {
            $this->sql['orderBy'] .= ", {$column} {$order} ";
        }
        return $this;
    }

    /**
     * 分组
     *
     * @param string $column 要设置分组的字段名
     *
     * @return $this
     */
    public function groupBy($column)
    {
        if ($this->sql['groupBy'] == '') {
            $this->sql['groupBy'] = "GROUP BY {$column} ";
        } else {
            $this->sql['groupBy'] .= ",{$column} ";
        }
        return $this;
    }

    /**
     * having语句
     *
     * @param string $column 字段名
     * @param string $operator 操作符
     * @param string $value 值
     *
     * @return $this
     */
    public function having($column, $operator = '=', $value)
    {
        $having = $this->sql['having'] == '' ? 'HAVING' : ',';
        $this->sql['having'] = "{$having} {$column}{$operator}%s ";
        $this->bindParams[] = $value;
        return $this;
    }

    /**
     * join内联结
     *
     * @param string|array $table 表名 要取别名时使用 [不带前缀表名 => 别名]
     * @param string $on 联结的条件 如：'c.cid = a.cid'
     * @param mixed $tablePrefix 表前缀
     *
     * @return $this
     */
    public function join($table, $on, $tablePrefix = null)
    {
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;

        $this->table($table, $tablePrefix);
        $hasAlias = is_array($table) ? true : false;

        $tableName = $tablePrefix . ($hasAlias ? key($table) : $table);
        $this->join[count($this->table) - 1 . '_' . $tableName] = is_array($on) ? $this->parseOn($table, $on) : addslashes($on);
        return $this;
    }

    /**
     * leftJoin左联结
     *
     * @param string|array $table 表名 要取别名时使用 [不带前缀表名 => 别名]
     * @param string $on 联结的条件 如：'c.cid = a.cid'
     * @param mixed $tablePrefix 表前缀
     *
     * @return $this
     */
    public function leftJoin($table, $on, $tablePrefix = null)
    {
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;

        $this->table($table, $tablePrefix);
        $hasAlias = is_array($table) ? true : false;

        $tableName = $tablePrefix . ($hasAlias ? key($table) : $table);
        $this->leftJoin[count($this->table) - 1 . '_' . $tableName] = is_array($on) ? $this->parseOn($table, $on) : addslashes($on);
        return $this;
    }

    /**
     * rightJoin右联结
     *
     * @param string|array $table 表名 要取别名时使用 [不带前缀表名 => 别名]
     * @param string $on 联结的条件 如：'c.cid = a.cid'
     * @param mixed $tablePrefix 表前缀
     *
     * @return $this
     */
    public function rightJoin($table, $on, $tablePrefix = null)
    {
        is_null($tablePrefix) && $tablePrefix = $this->tablePrefix;

        $this->table($table, $tablePrefix);
        $hasAlias = is_array($table) ? true : false;

        $tableName = $tablePrefix . ($hasAlias ? key($table) : $table);
        $this->rightJoin[count($this->table) - 1 . '_' . $tableName] = is_array($on) ? $this->parseOn($table, $on) : addslashes($on);
        return $this;
    }

    /**
     * union联结
     *
     * @param string|array $sql 要union的sql
     * @param bool $all 是否为union all
     *
     * @return $this
     */
    public function union($sql, $all = false)
    {
        if (is_array($sql)) {
            foreach ($sql as $s) {
                $this->union .= $all ? ' UNION ALL ' : ' UNION ';
                $this->union .= $this->filterUnionSql($s);
            }
        } else {
            $this->union .= $all ? ' UNION ALL ' : ' UNION ';
            $this->union .= $this->filterUnionSql($sql) . ' ';
        }
        return $this;
    }

    protected function filterUnionSql($sql)
    {
        return str_ireplace([
                'insert', "update", "delete", "\/\*", "\.\.\/", "\.\/", "union", "into", "load_file", "outfile"
            ],
            ["", "", "", "", "", "", "", "", "", ""],
            $sql);
    }

    /**
     * 解析联结的on参数
     *
     * @param string $table 要联结的表名
     * @param array $on ['on条件1', 'on条件2' => true] on条件为数字索引时多条件默认为and为非数字引时 条件=>true为and 条件=>false为or
     *
     * @return string
     */
    protected function parseOn(&$table, $on)
    {
        if (empty($on)) {
            throw new \InvalidArgumentException(Lang::get('_DB_PARAM_ERROR_PARSE_ON_', $table));
        }
        $result = '';
        foreach ($on as $key => $val) {
            if (is_numeric($key)) {
                $result == '' || $result .= ' AND ';
                $result .= $val;
            } else {
                $result == '' || $result .= ($val === true ? ' AND ' : ' OR ');
                $result .= $key;
            }
        }
        return addslashes($result); //on条件是程序员自己写死的表字段名不存在注入以防万一还是过滤一下
    }

    /**
     * orm参数是否自动重置, 默认在执行语句后会重置orm参数
     *
     * @param bool $autoReset 是否自动重置
     *
     * @return $this
     */
    public function paramsAutoReset($autoReset = true)
    {
        $this->paramsAutoReset = $autoReset;
        return $this;
    }

    /**
     * orm参数重置
     *
     */
    protected function reset()
    {
        if (!$this->paramsAutoReset) {
            $this->sql['columns'] = '';
            return;
        }

        $this->sql = [  //sql组装
            'where' => '',
            'columns' => '',
            'limit' => '',
            'orderBy' => '',
            'groupBy' => '',
            'having' => '',
        ];

        $this->table = []; //操作的表
        $this->join = []; //是否内联
        $this->leftJoin = []; //是否左联结
        $this->rightJoin = []; //是否右联
        $this->whereNeedAddAndOrOr = 0;
    }

    /**
     * 清空绑定的参数
     *
     */
    protected function clearBindParams()
    {
        if ($this->paramsAutoReset) {
            $this->bindParams = [];
        }
    }

    /**
     * SQL语句条件组装
     *
     * @param array $arr 要组装的数组
     *
     * @return string
     */
    protected function arrToCondition($arr)
    {
        $s = $p = '';
        $params = [];
        foreach ($arr as $k => $v) {
            if (is_array($v)) { //自增或自减
                switch (key($v)) {
                    case 'inc':
                        $p = "`{$k}`= `{$k}`+" . abs(intval(current($v)));
                        break;
                    case 'dec':
                        $p = "`{$k}`= `{$k}`-" . abs(intval(current($v)));
                        break;
                    case 'func':
                        $func = strtoupper(key(current($v)));
                        $funcParams = current(current($v));
                        foreach ($funcParams as $key => $val) {
                            if (!isset($dbFields[$val])) {
                                $funcParams[$key] = '%s';
                                $params[] = $val;
                            }
                        }
                        $p = "`{$k}`= {$func}(" . implode($funcParams, ',') . ')';
                        break;
                    default ://计算类型
                        $conkey = key($v);
                        if (!isset($dbFields[$conkey])) $conkey = $k;
                        if (!in_array(key(current($v)), ['+', '-', '*', '/', '%', '^', '&', '|', '<<', '>>', '~'])) {
                            throw new \InvalidArgumentException(Lang::get('_PARSE_UPDATE_SQL_PARAMS_ERROR_'));
                        }
                        $p = "`{$k}`= `{$conkey}`" . key(current($v)) . abs(intval(current(current($v))));
                        break;
                }
            } else {
                $p = "`{$k}`= %s";
                $params[] = $v;
            }

            $s .= (empty($s) ? '' : ',') . $p;
        }
        $this->bindParams = array_merge($params, $this->bindParams);
        return $s;
    }

    /**
     * SQL语句条件组装
     *
     * @param string $key eg: 'forum-fid-1-uid-2'
     * @param bool $and 多个条件之间是否为and  true为and false为or
     * @param bool $noCondition 是否为无条件操作  set/delete/update操作的时候 condition为空是正常的不报异常
     * @param bool $noTable 是否可以没有数据表 当delete/update等操作的时候已经执行了table() table为空是正常的
     *
     * @return array eg: ['forum', "`fid` = '1' AND `uid` = '2'"]
     */
    protected function parseKey($key, $and = true, $noCondition = false, $noTable = false)
    {
        $condition = '';
        $arr = explode('-', $key);
        $len = count($arr);
        for ($i = 1; $i < $len; $i += 2) {
            isset($arr[$i + 1]) && $condition .= ($condition ? ($and ? ' AND ' : ' OR ') : '') . "`{$arr[$i]}` = %s";
            $this->bindParams[] = $arr[$i + 1];
        }
        $table = strtolower($arr[0]);
        if (empty($table) && !$noTable) {
            throw new \InvalidArgumentException(Lang::get('_DB_PARAM_ERROR_PARSE_KEY_', $key, 'table'));
        }
        if (empty($condition) && !$noCondition) {
            throw new \InvalidArgumentException(Lang::get('_DB_PARAM_ERROR_PARSE_KEY_', $key, 'condition'));
        }
        empty($condition) || $condition = "($condition)";
        return [$table, $condition];
    }

    /**
     * 根据表名获取cache版本号
     *
     * @param string $table
     *
     * @return mixed
     */
    public function getCacheVer($table)
    {
        $version = Model::getInstance()->cache()->get($this->conf['mark'] . '_db_cache_version_' . $table);
        if (!$version) {
            $version = microtime(true);
            Model::getInstance()->cache()->set($this->conf['mark'] . '_db_cache_version_' . $table, $version, $this->conf['cache_expire']);
        }
        return $version;
    }

    /**
     * 设置cache版本号
     *
     * @param string $table
     */
    public function setCacheVer($table)
    {
        $isOpenEmergencyMode = Config::get('emergency_mode_not_real_time_refresh_mysql_query_cache');
        if ($isOpenEmergencyMode !== false && $isOpenEmergencyMode > 0) {//开启了紧急模式
            $expireTime = Model::getInstance()->cache()->get("emergency_mode_not_real_time_refresh_mysql_query_cache_{$table}");
            if ($expireTime && $isOpenEmergencyMode + $expireTime > time()) {
                return;
            }
            Model::getInstance()->cache()->set("emergency_mode_not_real_time_refresh_mysql_query_cache_{$table}", time(), 3600);
        }

        Model::getInstance()->cache()->set($this->conf['mark'] . '_db_cache_version_' . $table, microtime(true), $this->conf['cache_expire']);
    }
}
