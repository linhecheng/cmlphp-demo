<?php

define('CML_PROJECT_PATH', dirname(__DIR__));
define('CML_APP_PATH', 'proj3e5f9e47cd31239b6fd43a772c5a75b4');
$loader = require CML_PROJECT_PATH . DIRECTORY_SEPARATOR . 'vendor/autoload.php';
$loader->setPsr4('', CML_PROJECT_PATH . DIRECTORY_SEPARATOR . CML_APP_PATH . '/Application/');
\Cml\Cml::runApp();