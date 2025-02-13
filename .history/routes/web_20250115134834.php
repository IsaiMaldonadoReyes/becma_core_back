<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;



Route::post('login', [LoginController::class, 'login']);

Route::post('register', [LoginController::class, 'register']);

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();
