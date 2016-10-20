<?php
/**
 * author: seekerliu
 * createTime: 2016/10/20 下午3:37
 * Description:
 */
$baseDir = __DIR__ . '/../';
//Autoload
require_once $baseDir. 'vendor/autoload.php';

//ENV
$dotenv = new Dotenv\Dotenv($baseDir);
$dotenv->load();

//DDNS
$dns = new \App\Dns();
$dns->run();