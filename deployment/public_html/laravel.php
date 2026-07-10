<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Public document root: /smisul.bg/root
// Private Laravel app:  /smisul.bg/backend
$appPath = dirname(__DIR__).'/backend';

if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath.'/vendor/autoload.php';

/** @var Illuminate\Foundation\Application $app */
$app = require_once $appPath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
