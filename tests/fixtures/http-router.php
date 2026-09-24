<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_ends_with(config('database.connections.mongodb.database'), '_test')) {
    http_response_code(403);
    exit;
}
config(['services.cloudinary.cloud' => 'test', 'services.cloudinary.key' => 'key', 'services.cloudinary.secret' => 'secret']);
Http::preventStrayRequests();
Http::fake(['*cloudinary.com*' => Http::response(['secure_url' => 'https://images.example.test/native-put.png', 'resource_type' => 'image'])]);
$app->handleRequest(Request::capture());
