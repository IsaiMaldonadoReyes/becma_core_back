<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;

use App\Http\Controllers\AuthController;


Route::post('login', [LoginController::class, 'login']);

Route::post('register', [AuthController::class, 'register']);

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();
