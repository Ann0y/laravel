<?php

/**
 * 本地开发内置服务器路由（php -S 用）：
 * 存在的静态文件直接返回，其余请求交给 Laravel 前置控制器
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && (file_exists(__DIR__ . '/public' . $path)) && !is_dir(__DIR__ . '/public' . $path)) {
    return false;
}

require __DIR__ . '/public/index.php';
