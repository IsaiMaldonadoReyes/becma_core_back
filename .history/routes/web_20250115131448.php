<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;



Route::post('login', [LoginController::class, 'login']);



Route::get('/', function () {
    return view('welcome');
});

Auth::routes();
