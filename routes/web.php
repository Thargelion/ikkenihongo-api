<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::view('/docs', 'docs');
Route::get('/openapi.yaml', fn () => response()->file(base_path('openapi.yaml'), [
    'Content-Type' => 'application/yaml',
]));

require __DIR__.'/auth.php';
