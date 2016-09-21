<?php
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 14-2-8 下午3:07
 * @version  2.7
 * cml框架 视图 Json渲染引擎
 * *********************************************************** */
namespace Cml\View;

use Cml\Cml;
use Cml\Config;
use Cml\Debug;

/**
 * 视图 Json渲染引擎
 *
 * @package Cml\View
 */
class Json extends Base
{

    /**
     * 输出数据
     *
     */
    public function display() {
        header('Content-Type: application/json;charset='.Config::get('default_charset'));
        if (Cml::$debug) {
            $sql = Debug::getSqls();
            if (Config::get('dump_use_php_console')) {
                $sql && \Cml\dumpUsePHPConsole($sql, 'sql');
                \Cml\dumpUsePHPConsole(Debug::getTipInfo(), 'tipInfo');
                \Cml\dumpUsePHPConsole(Debug::getIncludeFiles(), 'includeFile');
            } else {
                if (isset($sql[0])) {
                    $this->args['sql'] = implode($sql, ', ');
                }
            }
        } else {
            $deBugLogData = \Cml\dump('', 1);
            if (!empty($deBugLogData)) {
                Config::get('dump_use_php_console') ? \Cml\dumpUsePHPConsole($deBugLogData, 'debug') : $this->args['cml_debug_info'] = $deBugLogData;
            }
        }

        exit(json_encode($this->args, PHP_VERSION >= '5.4.0' ? JSON_UNESCAPED_UNICODE : 0));
    }

}