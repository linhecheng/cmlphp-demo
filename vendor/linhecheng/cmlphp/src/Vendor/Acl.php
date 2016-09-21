<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-11 下午2:23
 * @version  2.7
 * cml框架 权限控制类
 * *********************************************************** */
namespace Cml\Vendor;

use Cml\Cml;
use Cml\Config;
use Cml\Encry;
use Cml\Http\Cookie;
use Cml\Model;

/**
   权限控制类

    对方法注释 @noacl 则不检查该方法的权限
    对方法注释 @acljump web/User/add 则将当前方法的权限检查跳转为检查 web/User/add方法的权限
    加到normal.php配置中
    //权限控制配置
    'administratorid'=>'1', //超管理员id

    建库语句
    CREATE TABLE `hadm_access` (
        `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '权限ID',
        `userid` int(11) DEFAULT '0' COMMENT '所属用户权限ID',
        `groupid` smallint(3) DEFAULT '0' COMMENT '所属群组权限ID',
        `menuid` int(11) NOT NULL DEFAULT '0' COMMENT '权限模块ID',
        PRIMARY KEY (`id`),
        KEY `idx_userid` (`userid`) USING BTREE,
        KEY `idx_groupid` (`groupid`) USING BTREE,
        KEY `idx_menuid` (`menuid`) USING BTREE
    ) ENGINE=MyISAM AUTO_INCREMENT=1038 DEFAULT CHARSET=utf8 COMMENT='用户或者用户组权限记录';

    CREATE TABLE `hadm_group` (
        `id` smallint(3) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(150) DEFAULT NULL,
        `status` tinyint(1) unsigned DEFAULT '1' COMMENT '1正常，0删除',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

    CREATE TABLE `hadm_menu` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `pid` int(11) NOT NULL DEFAULT '0' COMMENT '父模块ID编号 0则为顶级模块',
        `title` char(64) NOT NULL COMMENT '标题',
        `url` char(64) NOT NULL COMMENT 'url路径',
        `isshow` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示',
        `order` int(4) NOT NULL DEFAULT '0' COMMENT '排序倒序',
        PRIMARY KEY (`id`),
        KEY `idex_pid` (`pid`) USING BTREE,
        KEY `idex_order` (`order`) USING BTREE,
        KEY `idx_action` (`url`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='权限模块信息表';

    CREATE TABLE `hadm_users` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `groupid` varchar(255) NOT NULL DEFAULT '',
        `username` varchar(40) NOT NULL DEFAULT '',
        `password` varchar(40) NOT NULL DEFAULT '',
        `lastlogin` int(10) unsigned NOT NULL DEFAULT '0',
        `ctime` int(10) unsigned NOT NULL DEFAULT '0',
        `stime` int(10) unsigned NOT NULL DEFAULT '0',
        `status` tinyint(1) unsigned DEFAULT '1' COMMENT '1正常，0删除',
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`)
    ) ENGINE=MyISAM AUTO_INCREMENT=28 DEFAULT CHARSET=utf8;

@package Cml\Vendor
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
     * 设置加密用的混淆key Cookie::set本身有一重加密 这里再加一重
     *
     * @param string $key
     */
    public static function setEncryptKey($key)
    {
        self::$encryptKey = $key;
    }

    /**
     * 单点登录标识
     *
     * @var string
     */
    private static $ssoSign = '';


    /**
     * 保存当前登录用户的信息
     *
     * @param int $uid 用户id
     * @param bool $sso 是否为单点登录，即踢除其它登录用户
     */
    public static function setLoginStatus($uid, $sso = true)
    {
        $user = [
            'uid' => $uid,
            'expire' => Cml::$nowTime + 3600,
            'ssosign' => $sso ? (string)Cml::$nowMicroTime : self::$ssoSign
        ];

        //Cookie::set本身有一重加密 这里再加一重
        $sso && Model::getInstance()->cache()->set("SSOSingleSignOn{$uid}", (string)Cml::$nowMicroTime);
        Cookie::set(Config::get('userauthid'), Encry::encrypt(json_encode($user, PHP_VERSION >= '5.4.0' ? JSON_UNESCAPED_UNICODE : 0), self::$encryptKey), 0);
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
                || self::$authUser['expire'] < Cml::$nowTime
                ||  self::$authUser['ssosign'] != Model::getInstance()->cache()
                    ->get("SSOSingleSignOn".self::$authUser['uid'] )
            ) {
                self::$authUser = false;
                self::$ssoSign = '';
            } else {
                self::$ssoSign = self::$authUser['ssosign'];

                $user = Model::getInstance()->db()->get('users-id-'.self::$authUser['uid'].'-status-1');
                if (empty($user)) {
                    self::$authUser = false;
                } else {
                    $user = $user[0];
                    $tmp = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'nickname' => $user['nickname'],
                        'groupid' => explode('|', trim($user['groupid'], '|'))
                    ];
                    $groups = Model::getInstance()->db()->table('groups')
                        ->columns('name')
                        ->whereIn('id', $tmp['groupid'])
                        ->_and()
                        ->where('status', 1)
                        ->select();

                    $tmp['groupname'] = [];
                    foreach($groups as $group) {
                        $tmp['groupname'][] = $group['name'];
                    }

                    $tmp['groupname'] = implode(',', $tmp['groupname']);
                    //有操作登录超时时间重新设置为1个小时
                    if (self::$authUser['expire'] - Cml::$nowTime < 1800) {
                        self::setLoginStatus($user['id'], false);
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
            $appPosition = strpos($controller, '\\');
            $subString = substr($controller, 0, $appPosition).'\\Controller'. substr($controller, $appPosition, $actionPosition - $appPosition);
            $controller = "\\{$subString}Controller";

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
            $methods   = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
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
                            foreach($aclJump[1] as $val) {
                                trim($val) && $checkUrlArray[] = ltrim(str_replace('\\', '/', trim($val)), '/') ;
                            }
                        }
                        empty($checkUrlArray) || $checkUrl = $checkUrlArray;
                    }
                }
            }
        }

        $acl = Model::getInstance()->db()
            ->columns('m.id')
            ->table(['access'=> 'a'])
            ->join(['menus' => 'm'], 'a.menuid=m.id')
            ->lBrackets()
            ->whereIn('a.groupid', $authInfo['groupid'])
            ->_or()
            ->where('a.userid', $authInfo['id'])
            ->rBrackets();

        $acl = is_array($checkUrl) ? $acl->whereIn('m.url', $checkUrl) : $acl->where('m.url', $checkUrl);
        $acl = $acl->select();
        return (count($acl) > 0);
    }

    /**
     * 获取有权限的菜单列表
     *
     * @return array
     */
    public static function getMenus()
    {
        $res = [];
        $authInfo = self::getLoginInfo();
        if (!$authInfo) { //登录超时
            return $res;
        }

        Model::getInstance()->db()->table(['menus'=> 'm'])
            ->columns(['distinct m.id', 'm.pid', 'm.title', 'm.url']);

        //当前登录用户是否为超级管理员
        if (!self::isSuperUser()) {
            Model::getInstance()->db()
                ->join(['access'=> 'a'], 'a.menuid=m.id')
                ->lBrackets()
                ->whereIn('a.groupid', $authInfo['groupid'])
                ->_or()
                ->where('a.userid', $authInfo['id'])
                ->rBrackets()
                ->_and();
        }

        $result =  Model::getInstance()->db()->where('m.isshow', 1)
            ->orderBy('m.sort', 'DESC')
            ->orderBy('m.id','ASC')
            ->limit(0, 5000)
            ->select();

        $res = Tree::getTreeNoFormat($result, 0);
        return $res;
    }

    /**
     * 登出
     *
     */
    public static function logout()
    {
        $user = Acl::getLoginInfo();
        $user && Model::getInstance()->cache()->delete("SSOSingleSignOn".$user['id']);
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