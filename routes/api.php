<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return 'API test becma-core';
});



Route::post('login', [AuthController::class, 'login'])->middleware('web');

//Route::post('register', [AuthController::class, 'register']);



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::middleware(['auth:sanctum'])->group(function () {

    // Obtención de información general

    # Rutas a las que tiene el usuario acceso
    Route::get('authDirectories', [AuthController::class, 'authDirectories']);

    # Información personal del usuario logeado
    Route::get('authUserInformation', [AuthController::class, 'authUserInformation']);
});
