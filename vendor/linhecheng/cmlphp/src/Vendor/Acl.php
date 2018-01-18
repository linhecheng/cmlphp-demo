<?php
/* * *********************************************************
 * [cmlphp] (C)2012 - 3000 http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-11 下午2:23
 * @version  @see \Cml\Cml::VERSION
 * cmlphp框架 权限控制类
 * *********************************************************** */

namespace Cml\Vendor;

use Cml\Cml;
use Cml\Config;
use Cml\Encry;
use Cml\Http\Cookie;
use Cml\Model;

/**
 * 权限控制类
 *
 * 对方法注释 @noacl 则不检查该方法的权限
 * 对方法注释 @acljump web/User/add 则将当前方法的权限检查跳转为检查 web/User/add方法的权限
 * 加到normal.php配置中
 * //权限控制配置
 * 'administratorid'=>'1', //超管理员id
 *
 * 建库语句
 *
 * CREATE TABLE `pr_admin_app` (
 * `id` smallint(6) unsigned NOT NULL AUTO_INCREMENT,
 * `name` varchar(255) NOT NULL DEFAULT '' COMMENT '应用名',
 * PRIMARY KEY (`id`)
 * ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
 *
 * CREATE TABLE `pr_admin_access` (
 * `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '权限ID',
 * `userid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '所属用户权限ID',
 * `groupid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '所属群组权限ID',
 * `menuid` int(11) NOT NULL DEFAULT '0' COMMENT '权限模块ID',
 * PRIMARY KEY (`id`),
 * KEY `idx_userid` (`userid`),
 * KEY `idx_groupid` (`groupid`)
 * ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='用户或者用户组权限记录';
 *
 * CREATE TABLE `pr_admin_groups` (
 * `id` smallint(3) unsigned NOT NULL AUTO_INCREMENT,
 * `name` varchar(150) NOT NULL DEFAULT '' COMMENT '用户组名',
 * `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '1正常，0删除',
 * `remark` text NOT NULL COMMENT '备注',
 * PRIMARY KEY (`id`)
 * ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
 *
 * CREATE TABLE `pr_admin_menus` (
 * `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增id',
 * `pid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '父模块ID编号 0则为顶级模块',
 * `title` varchar(64) NOT NULL DEFAULT '' COMMENT '标题',
 * `url` varchar(64) NOT NULL DEFAULT '' COMMENT 'url路径',
 * `params` varchar(64) NOT NULL DEFAULT '' COMMENT 'url参数',
 * `isshow` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '是否显示',
 * `sort` smallint(3) unsigned NOT NULL DEFAULT '0' COMMENT '排序倒序',
 *  `app` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '菜单所属app，对应app表中的主键',
 * PRIMARY KEY (`id`),
 * KEY `idex_pid` (`pid`),
 * KEY `idex_order` (`sort`),
 * KEY `idx_action` (`url`),
 * KEY `idx_app` (`app`)
 * ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='权限模块信息表';
 *
 * CREATE TABLE `pr_admin_users` (
 * `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
 * `groupid` varchar(255) NOT NULL DEFAULT '0' COMMENT '用户组id',
 * `username` varchar(40) NOT NULL DEFAULT '' COMMENT '用户名',
 * `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '昵称',
 * `password` char(32) NOT NULL DEFAULT '' COMMENT '密码',
 * `lastlogin` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最后登录时间',
 * `ctime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
 * `stime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '修改时间',
 * `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '1正常，0删除',
 * `remark` text NOT NULL,
 * `from_type` tinyint(3) unsigned DEFAULT '1' COMMENT '用户类型。1为系统用户。',
 * PRIMARY KEY (`id`),
 * UNIQUE KEY `username` (`username`)
 * ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
 *
 * @package Cml\Vendor
 */
class Acl
{
    /**
     * 加密用的混淆key
     *
     * @var string
     */
    private static $encryptKey = 'pnnle-oienngls-llentne-lnegxe';

    /**
     * 定义表名
     *
     * @var array
     */
    private static $tables = [
        'access' => 'access',
        'groups' => 'groups',
        'menus' => 'menus',
        'users' => 'users',
    ];

    /**
     * 有权限的时候保存权限的显示名称用于记录log
     *
     * @var array
     */
    public static $aclNames = [];

    /**
     * 当前登录的用户信息
     *
     * @var null
     */
    public static $authUser = null;

    /**
     * 单点登录标识
     *
     * @var string
     */
    private static $ssoSign = '';

    /**
     * 设置加密用的混淆key Cookie::set本身有一重加密 这里再加一重
     *
     * @param string $key
     */
    public static function setEncryptKey($key)
    {
        self::$encryptKey = $key;
    }

    /**
     * 自定义表名
     *
     * @param string|array $type
     * @param string $tableName
     */
    public static function setTableName($type = 'access', $tableName = 'access')
    {
        if (is_array($type)) {
            self::$tables = array_merge(self::$tables, $type);
        } else {
            self::$tables[$type] = $tableName;
        }
    }

    /**
     * 获取表名
     * @param string $type
     *
     * @return mixed
     */
    public static function getTableName($type = 'access')
    {
        if (isset(self::$tables[$type])) {
            return self::$tables[$type];
        } else {
            throw new \InvalidArgumentException($type);
        }
    }

    /**
     * 保存当前登录用户的信息
     *
     * @param int $uid 用户id
     * @param bool $sso 是否为单点登录，即踢除其它登录用户
     * @param int $cookieExpire 登录的过期时间，为0则默认保持到浏览器关闭，> 0的值为登录有效期的秒数。默认为0
     * @param int $notOperationAutoLogin 当$cookieExpire设置为0时，这个值为用户多久不操作则自动退出。默认为1个小时
     */
    public static function setLoginStatus($uid, $sso = true, $cookieExpire = 0, $notOperationAutoLogin = 3600)
    {
        $cookieExpire > 0 && $notOperationAutoLogin = 0;
        $user = [
            'uid' => $uid,
            'expire' => $notOperationAutoLogin > 0 ? Cml::$nowTime + $notOperationAutoLogin : 0,
            'ssosign' => $sso ? (string)Cml::$nowMicroTime : self::$ssoSign
        ];
        $notOperationAutoLogin > 0 && $user['not_op'] = $notOperationAutoLogin;

        //Cookie::set本身有一重加密 这里再加一重
        if ($sso) {
            Model::getInstance()->cache()->set("SSOSingleSignOn{$uid}", $user['ssosign'], 86400 + $cookieExpire);
        } else {
            //如果是刚刚从要单点切换成不要单点。这边要把ssosign置为cache中的
            empty($user['ssosign']) && $user['ssosign'] = Model::getInstance()->cache()->get("SSOSingleSignOn{$uid}");
        }
        Cookie::set(Config::get('userauthid'), Encry::encrypt(json_encode($user, JSON_UNESCAPED_UNICODE), self::$encryptKey), $cookieExpire);
    }

    /**
     * 获取当前登录用户的信息
     *
     * @return array
     */
    public static function getLoginInfo()
    {
        if (is_null(self::$authUser)) {
            //Cookie::get本身有一重解密 这里解第二重
            self::$authUser = Encry::decrypt(Cookie::get(Config::get('userauthid')), self::$encryptKey);
            empty(self::$authUser) || self::$authUser = json_decode(self::$authUser, true);

            if (
                empty(self::$authUser)
                || (self::$authUser['expire'] > 0 && self::$authUser['expire'] < Cml::$nowTime)
                || self::$authUser['ssosign'] != Model::getInstance()->cache()
                    ->get("SSOSingleSignOn" . self::$authUser['uid'])
            ) {
                self::$authUser = false;
                self::$ssoSign = '';
            } else {
                self::$ssoSign = self::$authUser['ssosign'];

                $user = Model::getInstance()->db()->get(self::$tables['users'] . '-id-' . self::$authUser['uid'] . '-status-1');
                if (empty($user)) {
                    self::$authUser = false;
                } else {
                    $user = $user[0];
                    $tmp = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'nickname' => $user['nickname'],
                        'groupid' => explode('|', trim($user['groupid'], '|')),
                        'from_type' => $user['from_type']
                    ];
                    $groups = Model::getInstance()->db()->table(self::$tables['groups'])
                        ->columns('name')
                        ->whereIn('id', $tmp['groupid'])
                        ->where('status', 1)
                        ->select();

                    $tmp['groupname'] = [];
                    foreach ($groups as $group) {
                        $tmp['groupname'][] = $group['name'];
                    }

                    $tmp['groupname'] = implode(',', $tmp['groupname']);
                    //有操作登录超时时间重新设置为expire时间
                    if (self::$authUser['expire'] > 0 && (
                            (self::$authUser['expire'] - Cml::$nowTime) < (self::$authUser['not_op'] / 2)
                        )
                    ) {
                        self::setLoginStatus($user['id'], false, 0, self::$authUser['not_op']);
                    }

                    unset($user, $group);
                    self::$authUser = $tmp;
                }
            }
        }
        return self::$authUser;
    }


    /**
     * 检查对应的权限
     *
     * @param object|string $controller 传入控制器实例对象，用来判断当前访问的方法是不是要跳过权限检查。
     * 如当前访问的方法为web/User/list则传入new \web\Controller\User()获得的实例。最常用的是在基础控制器的init方法或构造方法里传入$this。
     * 传入字符串如web/User/list时会自动 new \web\Controller\User()获取实例用于判断
     *
     * @return int 返回1是通过检查，0是不能通过检查
     */
    public static function checkAcl($controller)
    {
        $authInfo = self::getLoginInfo();
        if (!$authInfo) return false; //登录超时

        //当前登录用户是否为超级管理员
        if (self::isSuperUser()) {
            return true;
        }

        $checkUrl = Cml::getContainer()->make('cml_route')->getFullPathNotContainSubDir();
        $checkAction = Cml::getContainer()->make('cml_route')->getActionName();

        if (is_string($controller)) {
            $checkUrl = trim($controller, '/\\');
            $controller = str_replace('/', '\\', $checkUrl);
            $actionPosition = strrpos($controller, '\\');
            $checkAction = substr($controller, $actionPosition + 1);
            $offset = $appPosition = 0;
            for ($i = 0; $i < Config::get('route_app_hierarchy', 1); $i++) {
                $appPosition = strpos($controller, '\\', $offset);
                $offset = $appPosition + 1;
            }
            $appPosition = $offset - 1;

            $subString = substr($controller, 0, $appPosition) . '\\' . Cml::getApplicationDir('app_controller_path_name') . substr($controller, $appPosition, $actionPosition - $appPosition);
            $controller = "\\{$subString}" . Config::get('controller_suffix');

            if (class_exists($controller)) {
                $controller = new $controller;
            } else {
                return false;
            }
        }

        $checkUrl = ltrim(str_replace('\\', '/', $checkUrl), '/');

        if (is_object($controller)) {
            //判断是否有标识 @noacl 不检查权限
            $reflection = new \ReflectionClass($controller);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                if ($method->name == $checkAction) {
                    $annotation = $method->getDocComment();
                    if (strpos($annotation, '@noacl') !== false) {
                        return true;
                    }

                    $checkUrlArray = [];

                    if (preg_match('/@acljump([^\n]+)/i', $annotation, $aclJump)) {
                        if (isset($aclJump[1]) && $aclJump[1]) {
                            $aclJump[1] = explode('|', $aclJump[1]);
                            foreach ($aclJump[1] as $val) {
                                trim($val) && $checkUrlArray[] = ltrim(str_replace('\\', '/', trim($val)), '/');
                            }
                        }
                        empty($checkUrlArray) || $checkUrl = $checkUrlArray;
                    }
                }
            }
        }

        $acl = Model::getInstance()->db()
            ->table([self::$tables['access'] => 'a'])
            ->join([self::$tables['menus'] => 'm'], 'a.menuid=m.id')
            ->lBrackets()
            ->whereIn('a.groupid', $authInfo['groupid'])
            ->_or()
            ->where('a.userid', $authInfo['id'])
            ->rBrackets();

        $acl = is_array($checkUrl) ? $acl->whereIn('m.url', $checkUrl) : $acl->where('m.url', $checkUrl);
        $acl = $acl->count('1');
        return $acl > 0;
    }

    /**
     * 获取有权限的菜单列表
     *
     * @param bool $format 是否格式化返回
     * @param string $columns 要额外获取的字段
     *
     * @return array
     */
    public static function getMenus($format = true, $columns = '')
    {
        $res = [];
        $authInfo = self::getLoginInfo();
        if (!$authInfo) { //登录超时
            return $res;
        }

        Model::getInstance()->db()->table([self::$tables['menus'] => 'm'])
            ->columns(['distinct m.id', 'm.pid', 'm.title', 'm.url', 'm.params' . ($columns ? " ,{$columns}" : '')]);

        //当前登录用户是否为超级管理员
        if (!self::isSuperUser()) {
            Model::getInstance()->db()
                ->join([self::$tables['access'] => 'a'], 'a.menuid=m.id')
                ->lBrackets()
                ->whereIn('a.groupid', $authInfo['groupid'])
                ->_or()
                ->where('a.userid', $authInfo['id'])
                ->rBrackets()
                ->_and();
        }

        $result = Model::getInstance()->db()->where('m.isshow', 1)
            ->orderBy('m.sort', 'DESC')
            ->orderBy('m.id', 'ASC')
            ->limit(0, 5000)
            ->select();

        $res = $format ? Tree::getTreeNoFormat($result, 0) : $result;
        return $res;
    }

    /**
     * 登出
     *
     */
    public static function logout()
    {
        $user = Acl::getLoginInfo();
        $user && Model::getInstance()->cache()->delete("SSOSingleSignOn" . $user['id']);
        Cookie::delete(Config::get('userauthid'));
    }

    /**
     * 判断当前登录用户是否为超级管理员
     *
     * @return bool
     */
    public static function isSuperUser()
    {
        $authInfo = self::getLoginInfo();
        if (!$authInfo) {//登录超时
            return false;
        }
        return Config::get('administratorid') === intval($authInfo['id']);
    }
}
