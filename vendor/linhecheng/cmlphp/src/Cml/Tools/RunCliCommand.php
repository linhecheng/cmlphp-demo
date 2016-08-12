<?php namespace Cml\Tools;
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 2015/11/9 16:01
 * @version  2.6
 * cml框架 系统cli命令解析
 * *********************************************************** */
use Cml\Http\Request;

/**
 * 系统cli命令解析
 *
 * @package Cml\Tools
 */
class RunCliCommand
{
    /**
     * 判断从命令行执行的系统命令
     *
     */
    public static function runCliCommand()
    {
        Request::isCli() || exit('please run on cli!');

        if($_SERVER['argv'][1] != 'cml.cmd') {
            return ;
        }

        array_shift($_SERVER['argv']);
        array_shift($_SERVER['argv']);
        $tool = array_shift($_SERVER['argv']);
        $args = array();
        foreach($_SERVER['argv'] as $val) {
            $args[] = $val;
        }

        $deper = Request::isCli() ? PHP_EOL : '<br />';
        self::printMessage('//*********************cml.cmd start!**************************'.$deper);

        if (call_user_func_array('\\Cml\\Tools\\'.$tool, $args) === false) {
            self::printMessage("call result is false please check method is exists! or method return false?...");
        }

        self::printMessage($deper.'*********************cml.cmd end!**************************//');

        exit();
    }

    /**
     * 打印一行
     *
     * @param string $msg
     *
     * @return void
     */
    private static function printMessage($msg)
    {
        $deper = Request::isCli() ? PHP_EOL : '<br />';
        echo $deper.$msg .$deper;
    }
}