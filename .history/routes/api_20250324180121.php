<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\core\SistemaController;
use App\Http\Controllers\ExportController;

use App\Http\Controllers\comercial\Rpt2VentasPorMarcaController;

Route::get('/', function () {
    return 'API test becma-core';
});

Route::get('exportExcel', [ExportController::class, 'exportExcel']);

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

    Route::post('resetPassword', [AuthController::class, 'resetPassword']);

    Route::get('indexSistema', [SistemaController::class, 'index']);
    Route::post('storeSistema', [SistemaController::class, 'store']);
    Route::put('updateSistema/{id}', [SistemaController::class, 'update']);
    Route::delete('/destroySistema/{id}', [SistemaController::class, 'destroy']);
    Route::delete('/destroySistemaByIds', [SistemaController::class, 'destroyByIds']);


    // Rpt2 = Ventas por marcas
    Route::get('labelRtp2', [Rpt2VentasPorMarcaController::class, 'label']);
});
