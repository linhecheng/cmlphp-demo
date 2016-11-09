<?php
/* * *********************************************************
 * [cmlphp] (C)2012 - 3000 http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-13 下午1:51
 * @version  @see \Cml\Cml::VERSION
 * cmlphp框架 语言处理类
 * *********************************************************** */
namespace Cml;

/**
 * 语言包读写类、负责语言包的读取
 *
 * @package Cml
 */
class Lang extends Config
{
    /**
     * 存放了所有语言信息
     *
     * @var array
     */
    protected static $_content = [
        'normal' => []
    ];

    /**
     * 获取语言 不区分大小写
     *  获取值的时候可以动态传参转出语言值
     *  如：\Cml\Lang::get('_CML_DEBUG_ADD_CLASS_TIP_', '\Cml\Base') 取出_CML_DEBUG_ADD_CLASS_TIP_语言变量且将\Cml\base替换语言中的%s
     *
     * @param string $key 支持.获取多维数组
     * @param string $default 不存在的时候默认值
     *
     * @return string
     */
    public static function get($key = null, $default = '')
    {
        if (empty($key)) {
            return '';
        }
        $key = strtolower($key);
        $val = Cml::doteToArr($key, self::$_content['normal']);

        if (is_null($val)) {
            return is_array($default) ? '' : $default;
        } else {
            if (is_array($default)) {
                $keys = array_keys($default);
                $keys = array_map(function ($key) {
                    return '{' . $key . '}';
                }, $keys);
                return str_replace($keys, array_values($default), $val);
            } else {
                $replace = func_get_args();
                $replace[0] = $val;
                return call_user_func_array('sprintf', array_values($replace));
            }
        }
    }
}
