<?php

$rootPath = dirname(__DIR__);

require $rootPath . '/vendor/autoload.php';

date_default_timezone_set('UTC');

if (!file_exists($rootPath . '/.env')) {
    throw new \Exception('Remember to add .env file!');
}
$env = parse_ini_file($rootPath . '/.env');

foreach ($env as $key => $value) {
    putenv("$key=$value");
}

function root_path($path = '/') 
{
    global $rootPath;

    return str_replace(
        ['\\', '/'], 
        DIRECTORY_SEPARATOR, 
        $rootPath . DIRECTORY_SEPARATOR . trim($path, '\\/')
    );
}

function logger($data)
{
    $logFilePath = root_path('storage/logs/' . date('Y-m-d') . '.log');
    $contents = @file_get_contents($logFilePath) ?: '';

    file_put_contents($logFilePath, $contents . print_r($data, true) . "\n\n");
}