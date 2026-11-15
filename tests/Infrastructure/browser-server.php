<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\Support\TestClock;

// Explicit CLI router only; public/index.php never loads this file.
$public = realpath(__DIR__.'/../../public');
$file = realpath($public.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($file && is_file($file) && str_starts_with($file, $public.DIRECTORY_SEPARATOR)) {
    return false;
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing')) {
    http_response_code(404);
    exit;
}

Carbon::setTestNow(TestClock::NOW);
// A fixed domain clock must not create cookies already expired by wall time.
config(['session.expire_on_close' => true]);
// Ignore any active developer HMR server without modifying public/hot.
$app->make(Vite::class)->useHotFile(__DIR__.'/unused-hot');
$app->handleRequest(Request::capture());
