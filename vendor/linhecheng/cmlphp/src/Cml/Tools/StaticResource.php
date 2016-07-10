<?php namespace Cml\Tools;
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.51beautylife.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 2015/11/9 16:01
 * @version  2.5
 * cml框架 静态资源管理
 * *********************************************************** */
use Cml\Cml;
use Cml\Config;
use Cml\Http\Request;
use Cml\Http\Response;
use Cml\Route;

/**
 * 静态资源管理类
 *
 * @package Cml\Tools
 */
class StaticResource
{
    public static function createSymbolicLink($rootDir = null)
    {
        CML_IS_MULTI_MODULES || exit('please set is_multi_modules => true');

        $deper = (Request::isCli() ? PHP_EOL : '<br />');

        echo "{$deper}**************************create link start!*********************{$deper}";

        echo '|' . str_pad('', 64, ' ', STR_PAD_BOTH) . '|';

        is_null($rootDir) && $rootDir = CML_PROJECT_PATH . DIRECTORY_SEPARATOR . 'public';
        is_dir($rootDir) || mkdir($rootDir, true, 0700);
        //modules_static_path_name
        // 递归遍历目录
        $dirIterator = new \DirectoryIterator(CML_APP_MODULES_PATH);

        foreach ($dirIterator as $file) {
            if (!$file->isDot() && $file->isDir()) {
                $resourceDir = $file->getPathname() . DIRECTORY_SEPARATOR . Config::get('modules_static_path_name');
                if (is_dir($resourceDir)) {
                    $distDir = CML_PROJECT_PATH . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $file->getFilename();
                    $cmd = Request::operatingSystem() ? "mklink /d {$distDir} {$resourceDir}" : "ln -s {$resourceDir} {$distDir}";
                    exec($cmd, $result);
                    $tip = "create link Application [{$file->getFilename()}] result : ["
                        . (is_dir($distDir) ? 'true' : 'false') . "]";
                    $tip = str_pad($tip, 64, ' ', STR_PAD_BOTH);
                    print_r(
                        $deper . '|' . $tip . '|'
                    );
                }
            }
        }

        echo $deper . '|' . str_pad('', 64, ' ', STR_PAD_BOTH) . '|';
        echo("{$deper}****************************create link end!**********************{$deper}");
    }

    /**
     * 解析一个静态资源的地址
     *
     * @param string $resource 文件地址
     */
    public static function parseResourceUrl($resource = '')
    {
        //简单判断没有.的时候当作是目录不加版本号
        $isDir = strpos($resource, '.') === false ? true : false;
        if (Cml::$debug && CML_IS_MULTI_MODULES) {
            $file = Response::url("cmlframeworkstaticparse/{$resource}", false);
            if (Config::get('url_model') == 2 ) {
                $file = rtrim($file, Config::get('url_html_suffix'));
            }

            $isDir || $file .= ( Config::get("url_model") == 3 ? "&v=" : "?v=" ) . Cml::$nowTime;
        } else {
            $file = Config::get("static__path", Route::$urlParams["root"]."public/").$resource;
            $isDir || $file .= ( Config::get("url_model") == 3 ? "&v=" : "?v=" ) . Config::get('static_file_version');
        }
        echo $file;
    }

    /**
     * 解析一个静态资源的内容
     *
     */
    public static function parseResourceFile()
    {
        $pathinfo = Route::getPathInfo();
        array_shift($pathinfo);
        $resource = implode('/', $pathinfo);

        if (Cml::$debug && CML_IS_MULTI_MODULES) {
            $pos = strpos ($resource, '/');
            $file = CML_APP_MODULES_PATH . DIRECTORY_SEPARATOR.substr($resource, 0, $pos).DIRECTORY_SEPARATOR
                .Config::get('modules_static_path_name') . substr($resource, $pos);

            if (is_file($file)) {
                Response::sendContentTypeBySubFix(substr($resource, strrpos($resource, '.') + 1));
                exit(file_get_contents($file));
            } else {
                Response::sendHttpStatus(404);
            }
        }
    }
}
