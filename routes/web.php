<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api-docs-json', function () {
    $path = storage_path('api-docs/api-docs.json');
    if (!file_exists($path)) {
        abort(404, 'API Docs not found');
    }
    return response()->file($path, [
        'Content-Type' => 'application/json',
    ]);
});

Route::get('/api/test-web', function () {
    return 'Web OK';
});
