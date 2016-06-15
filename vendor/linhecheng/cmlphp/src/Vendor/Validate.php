<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.51beautylife.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-211 下午2:23
 * @version  2.5
 * cml框架 数据验证类
 * *********************************************************** */

namespace Cml\Vendor;

/**
 * C数据验证类,封装了常用的数据验证接口
 *
 * @package Cml\Vendor
 */
class Validate
{

    /**
     * 数据基础验证-检测字符串长度
     *
     * @param  string $value 需要验证的值
     * @param  int    $min   字符串最小长度
     * @param  int    $max   字符串最大长度
     *
     * @return bool
     */
    public static function isLength($value, $min = 0, $max = 0)
    {
        $value = trim($value);
        if (!is_string($value)) {
            return false;
        }
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);

        if ($min != 0 && $length < $min) return false;
        if ($max != 0 && $length > $max) return false;
        return true;
    }

    /**
     * 数据基础验证-是否必须填写的参数
     *
     * @param  string $value 需要验证的值
     *
     * @return bool
     */
    public static function isRequire($value)
    {
        return preg_match('/.+/', trim($value));
    }

    /**
     * 数据基础验证-是否是空字符串
     *
     * @param  string $value 需要验证的值
     *
     * @return bool
     */
    public static function isEmpty($value)
    {
        if (empty($value)) return true;
        return false;
    }

    /**
     * 数据基础验证-检测数组，数组为空时候也返回false
     *
     * @param  string $value 需要验证的值
     *
     * @return bool
     */
    public static function isArr($value)
    {
        if (!is_array($value) || empty($value)) return false;
        return true;
    }

    /**
     * 数据基础验证-是否是Email 验证：xxx@qq.com
     *
     * @param  string $value 需要验证的值
     *
     * @return bool
     */
    public static function isEmail($value)
    {
        return filter_var($value, \FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * 数据基础验证-是否是IP
     *
     * @param  string $value 需要验证的值
     *
     * @return bool
     */
    public static function isIp($value)
    {
        return filter_var($value, \FILTER_VALIDATE_IP) !== false;
    }

    /**
     * 数据基础验证-是否是数字类型
     *
     * @param  string $value 需要验证的值
     *
     * @return bool
     */
    public static function isNumber($value)
    {
        return is_numeric($value);
    }

    /**
     * 数据基础验证-是否是身份证
     *
     * @param  string $value 需要验证的值
     *
     * @return bool
     */
    public static function isCard($value)
    {
        return preg_match("/^(\d{15}|\d{17}[\dx])$/i", $value);
    }

    /**
     * 数据基础验证-是否是移动电话 验证：1385810XXXX
     *
     * @param  string $value 需要验证的值
     *
     * @return bool
     */
    public static function isMobile($value)
    {
        return preg_match('/^[+86]?1[354678][0-9]{9}$/', trim($value));
    }

    /**
     * 数据基础验证-是否是电话 验证：0571-xxxxxxxx
     *
     * @param  string $value 需要验证的值
     * @return bool
     */
    public static function isPhone($value)
    {
        return preg_match('/^[0-9]{3,4}[\-]?[0-9]{7,8}$/', trim($value));
    }

    /**
     * 数据基础验证-是否是URL 验证：http://www.baidu.com
     *
     * @param  string $value 需要验证的值
     *
     * @return bool
     */
    public static function isUrl($value)
    {
        return filter_var($value, \FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 数据基础验证-是否是邮政编码 验证：311100
     *
     * @param  string $value 需要验证的值
     *
     * @return bool
     */
    public static function isZip($value)
    {
        return preg_match('/^[1-9]\d{5}$/', trim($value));
    }

    /**
     * 数据基础验证-是否是QQ
     *
     * @param  string $value 需要验证的值
     *
     * @return bool
     */
    public static function isQq($value)
    {
        return preg_match('/^[1-9]\d{4,12}$/', trim($value));
    }

    /**
     * 数据基础验证-是否是英文字母
     *
     * @param  string $value 需要验证的值
     *
     * @return bool
     */
    public static function isEnglish($value)
    {
        return preg_match('/^[A-Za-z]+$/', trim($value));
    }

    /**
     * 数据基础验证-是否是中文
     *
     * @param  string $value 需要验证的值
     *
     * @return bool
     */
    public static function isChinese($value)
    {
        return preg_match("/^([\xE4-\xE9][\x80-\xBF][\x80-\xBF])+$/", trim($value));
    }


    /**
     * 检查是否是安全的账号
     *
     * @param string $value
     *
     * @return bool
     */
    public static function isSafeAccount($value)
    {
        return preg_match ("/^[a-zA-Z]{1}[a-zA-Z0-9_\.]{3,31}$/", $value);
    }

    /**
     * 检查是否是安全的昵称
     *
     * @param string $value
     *
     * @return bool
     */
    public static function isSafeNickname($value)
    {
        return preg_match ("/^[-\x{4e00}-\x{9fa5}a-zA-Z0-9_\.]{2,10}$/u", $value);
    }

    /**
     * 检查是否是安全的密码
     *
     * @param string $str
     *
     * @return bool
     */
    public static function isSafePassword($str)
    {
        if (preg_match('/[\x80-\xff]./', $str) || preg_match('/\'|"|\"/', $str) || strlen($str) < 6 || strlen($str) > 20 ){
            return false;
        }
        return true;
    }

    /**
     * 检查是否是正确的标识符
     *
     * @param string $value 以字母或下划线开始，后面跟着任何字母，数字或下划线。
     *
     * @return mixed
     */
    public static function isIdentifier($value)
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]+$/', trim($value));
    }

}