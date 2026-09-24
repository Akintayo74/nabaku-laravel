<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['message' => 'NABAKU api is running.']));
Route::get('/ready', function () {
    DB::connection('mongodb')->getMongoDB()->command(['ping' => 1]);

    return response()->json(['status' => 'ok']);
});
