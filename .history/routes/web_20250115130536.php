<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;


Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
});


Route::get('/', function () {
    return view('welcome');
});

Auth::routes();
