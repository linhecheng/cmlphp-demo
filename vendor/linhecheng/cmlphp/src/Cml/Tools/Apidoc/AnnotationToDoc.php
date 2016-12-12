<?php namespace Cml\Tools\Apidoc;

/* * *********************************************************
 * [cmlphp] (C)2012 - 3000 http://cmlphp.com
 * @Author  linhecheng<linhechengbush@live.com>
 * @Date: 2015/11/9 16:01
 * @version  @see \Cml\Cml::VERSION
 * cmlphp框架 从注释生成文档
 * *********************************************************** */
use Cml\Cml;
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
        $result = [];
        $config = Config::load('api', Config::get('route_app_hierarchy', 1) < 1 ? true : false);
        foreach ($config['version'] as $version => $apiList) {
            isset($result[$version]) || $result[$version] = [];
            foreach ($apiList as $model => $api) {
                $pos = strrpos($api, '\\');
                $controller = substr($api, 0, $pos);
                $action = substr($api, $pos + 1);
                if (class_exists($controller) === false) {
                    continue;
                }
                $annotationParams = self::getAnnotationParams($controller, $action);
                empty($annotationParams) || $result[$version][$model] = $annotationParams;
            }
        }

        foreach ($result as $key => $val) {
            if (count($val) < 1) {
                unset($result[$key]);
            }
        }

        $systemCode = Cml::requireFile(__DIR__ . DIRECTORY_SEPARATOR . 'resource' . DIRECTORY_SEPARATOR . 'code.php');


        Cml::requireFile(__DIR__ . DIRECTORY_SEPARATOR . 'resource' . DIRECTORY_SEPARATOR . 'doc.html', ['config' => $config, 'result' => $result, 'systemCode' => $systemCode]);
    }

    /**
     * 解析获取某控制器注释参数信息
     *
     * @param string $controller 控制器名
     * @param string $action 方法名
     *
     * @return array
     */
    public static function getAnnotationParams($controller, $action)
    {
        $result = [];

        $reflection = new \ReflectionClass($controller);
        $res = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($res as $method) {
            if ($method->name == $action) {
                $annotation = $method->getDocComment();
                if (strpos($annotation, '@doc') !== false) {
                    //$result[$version][$model]['all'] = $annotation;
                    //描述
                    preg_match('/@desc([^\n]+)/', $annotation, $desc);
                    $result['desc'] = isset($desc[1]) ? trim($desc[1]) : '';
                    //参数
                    preg_match_all('/@param([^\n]+)/', $annotation, $params);
                    foreach ($params[1] as $key => $val) {
                        $tmp = explode(' ', preg_replace('/\s(\s+)/', ' ', trim($val)));
                        isset($tmp[3]) || $tmp[3] = 'N';
                        substr($tmp[1], 0, 1) == '$' && $tmp[1] = substr($tmp[1], 1);
                        $result['params'][] = $tmp;
                    }

                    //请求示例
                    preg_match('/@req(.+?)(\*\s*?@|\*\/)/s', $annotation, $reqEg);
                    $result['req'] = isset($reqEg[1]) ? self::formatCode($reqEg[1]) : '';

                    //请求成功示例
                    preg_match('/@success(.+?)(\*\s*?@|\*\/)/s', $annotation, $success);
                    $result['success'] = isset($success[1]) ? self::formatCode($success[1]) : '';

                    //请求失败示例
                    preg_match('/@error(.+?)(\*\s*?@|\*\/)/s', $annotation, $error);
                    $result['error'] = isset($error[1]) ? self::formatCode($error[1]) : '';
                }
            }
        }
        return $result;
    }

    /**
     * 格式化json代码
     *
     * @param $code
     *
     * @return string
     */
    private static function formatCode($code)
    {
        $code = array_map(function ($val) {
            return trim(ltrim(trim($val), '*'));
        }, explode("\n", trim($code)));
        $dep = 0;

        foreach ($code as $lineNum => &$line) {
            $pos = strpos($line, '//');
            $pos || $pos = strpos($line, '#');
            $wordLine = $pos === false ? $line : trim(substr($line, 0, $pos));
            $firstWord = substr($wordLine, 0, 1);
            $lastWord = substr($wordLine, -1);
            $lineNum === 0 && $line !== '{' && $line = "{\n    " . substr($line, 1);

            //本行就要减空格
            if (
                $firstWord === '}'//行首
                || $firstWord === ']' //行首
                || ($lastWord === '}' && false === strpos($line, '{'))
                || ($lastWord === ']' && false === strpos($line, '['))
            ) {
                --$dep;
            }

            $line = str_pad("", $dep * 4, " ", STR_PAD_LEFT) . $line;

            //下一行加空格
            if (
                $lastWord === '{'
                || $lastWord === '['
                || ($firstWord === '{' && false === strpos($line, '}'))
                || ($firstWord === '[' && false === strpos($line, ']'))
            ) {
                ++$dep;
            }
        }

        return implode("\n", $code);
    }
}
