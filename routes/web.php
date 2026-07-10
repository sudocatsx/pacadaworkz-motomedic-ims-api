<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DummyController;

Route::get('/', function () {
    return view('welcome');
});



Route::get('/api/dummy', [DummyController::class, 'dummy']);
