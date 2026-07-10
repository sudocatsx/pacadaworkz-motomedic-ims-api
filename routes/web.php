<?php

use App\Http\Controllers\DummyController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/dummy', [DummyController::class, 'dummy']);
