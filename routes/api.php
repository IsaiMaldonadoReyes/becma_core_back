<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\core\SistemaController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\core\EmpresaController;

use App\Http\Controllers\comercial\Rpt2VentasPorMarcaController;

use App\Http\Controllers\nomina\CatalogosController;
use App\Http\Controllers\nomina\DispersionController;

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

    Route::post('resetPassword', [AuthController::class, 'resetPassword']);

    Route::get('indexSistema', [SistemaController::class, 'index']);
    Route::post('storeSistema', [SistemaController::class, 'store']);
    Route::put('updateSistema/{id}', [SistemaController::class, 'update']);
    Route::delete('/destroySistema/{id}', [SistemaController::class, 'destroy']);
    Route::delete('/destroySistemaByIds', [SistemaController::class, 'destroyByIds']);


    // empresas

    Route::post('rptEmpresas', [EmpresaController::class, 'rptEmpresas']);

    // Rpt2 = Ventas por marcas
    Route::post('labelRpt2', [Rpt2VentasPorMarcaController::class, 'label']);
    Route::post('dataRpt2', [Rpt2VentasPorMarcaController::class, 'dataset']);
    Route::post('marcasRpt2', [Rpt2VentasPorMarcaController::class, 'marcas']);



    Route::post('exportExcel', [ExportController::class, 'exportExcel']);


    // Listar todas las empresas de nomina

    Route::post('empresasNominas', [EmpresaController::class, 'empresasNominas']);


    // SATCatTipoContrato
    Route::post('nominaTipoContrato/{id}', [CatalogosController::class, 'tipoContrato']);

    // TipoPeriodo
    Route::post('nominaTipoPeriodo/{id}', [CatalogosController::class, 'tipoPeriodo']);

    // Departamento
    Route::post('nominaDepartamento/{id}', [CatalogosController::class, 'departamento']);

    // Puesto
    Route::post('nominaPuesto/{id}', [CatalogosController::class, 'puesto']);

    // Tipo prestacion
    Route::post('nominaTipoPrestacion/{id}', [CatalogosController::class, 'tipoPrestacion']);

    // Tipo prestacion
    Route::post('nominaTurno/{id}', [CatalogosController::class, 'turno']);

    // SATCatTipoContrato
    Route::post('nominaTipoRegimen/{id}', [CatalogosController::class, 'tipoRegimen']);

    // RegistroPatronal
    Route::post('nominaRegistroPatronal/{id}', [CatalogosController::class, 'registroPatronal']);

    // EntidadFederativa
    Route::post('nominaEntidadFederativa/{id}', [CatalogosController::class, 'entidadFederativa']);

    // Bancos
    Route::post('nominaBanco/{id}', [CatalogosController::class, 'bancos']);

    // Empresa
    Route::post('nominaEmpresa/{id}', [CatalogosController::class, 'empresa']);

    // Empresa
    Route::post('nominaTipoJornada/{id}', [CatalogosController::class, 'tipoJornada']);

    Route::post('dispersion', [DispersionController::class, 'exportar']);
});
