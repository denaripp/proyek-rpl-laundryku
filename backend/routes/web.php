<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name', 'LaundryKu'),
        'type' => 'REST API',
        'status' => 'active',
        'api_version' => 'v1',
    ]);
});
