<?php namespace Cml\Tools\Apidoc;
/* * *********************************************************
 * [cml] (C)2012 - 3000 cml http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 2015/11/9 16:01
 * @version  2.6
 * cml框架 从注释生成文档
 * *********************************************************** */
use Cml\Config;

/**
 * 从注释生成文档实现类
 *
 * @package Cml\Tools\Apidoc
 */
class AnnotationToDoc
{
    /**
     * 从注释解析生成文档
     *
     */
    public static function parse()
    {
        $result = array();
        $config = Config::load('api', Config::get('is_multi_modules') ? false : true);
        foreach($config['version'] as $version => $apiList) {
            isset($result[$version]) || $result[$version] = array();
            foreach($apiList as $model => $api) {
                $pos = strrpos($api, '\\');
                $controller = substr($api, 0, $pos);
                $action = substr($api, $pos + 1);
                if (class_exists($controller) === false) {
                    continue;
                }
                $reflection = new \ReflectionClass($controller);
                $res   = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
                foreach($res as $method) {
                    if ($method->name == $action) {
                        $annotation = $method->getDocComment();
                        if (strpos($annotation, '@doc') !== false) {
                            $result[$version][$model] = array();
                            //$result[$version][$model]['all'] = $annotation;
                            //描述
                            preg_match('/@desc([^\n]+)/', $annotation, $desc);
                            $result[$version][$model]['desc'] = isset($desc[1]) ? $desc[1] : '';
                            //参数
                            preg_match_all('/@param([^\n]+)/', $annotation, $params);
                            foreach($params[1] as $key => $val) {
                                $tmp = explode(' ', preg_replace('/\s(\s+)/', ' ', trim($val)));
                                isset($tmp[3]) || $tmp[3] = 'N';
                                substr($tmp[1], 0, 1) == '$' && $tmp[1] = substr($tmp[1], 1);
                                $result[$version][$model]['params'][] = $tmp;
                            }

                            //请求示例
                            preg_match('/@req(.+?)(\*\s*?@|\*\/)/s', $annotation, $reqEg);
                            $result[$version][$model]['req'] = isset($reqEg[1]) ? $reqEg[1] : '';
                            //请求成功示例
                            preg_match('/@success(.+?)(\*\s*?@|\*\/)/s', $annotation, $success);
                            $result[$version][$model]['success'] = isset($success[1]) ? $success[1] : '';
                            //请求失败示例
                            preg_match('/@error(.+?)(\*\s*?@|\*\/)/s', $annotation, $error);
                            $result[$version][$model]['error'] = isset($error[1]) ? $error[1] : '';
                        }
                    }
                }
            }
        }

        foreach($result as $key => $val) {
            if (count($val) < 1) {
                unset($result[$key]);
            }
        }
        
        $systemCode = require __DIR__ . DIRECTORY_SEPARATOR . 'resource' . DIRECTORY_SEPARATOR .'code.php';

        require __DIR__ . DIRECTORY_SEPARATOR . 'resource' . DIRECTORY_SEPARATOR . 'doc.html';
    }
}