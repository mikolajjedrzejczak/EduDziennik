<?php

// Explicit test-server entry point. Never included from public/index.php.
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

// Let the PHP test server serve built fonts from its public document root.
$public = realpath(__DIR__.'/../../../public');
$file = realpath($public.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($file && is_file($file) && str_starts_with($file, $public.DIRECTORY_SEPARATOR)) {
    return false;
}

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing')) {
    http_response_code(404);
    exit;
}
$app['view']->getFinder()->prependLocation(__DIR__);
require __DIR__.'/routes.php';
$app->handleRequest(Request::capture());
