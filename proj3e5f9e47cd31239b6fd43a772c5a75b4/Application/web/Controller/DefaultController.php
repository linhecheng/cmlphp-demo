<?php namespace web\Controller;

use Cml\Controller;

class DefaultController extends Controller
{
    public function index()
    {
        echo '欢迎使用cml框架,应用初始化成功';
    }
}