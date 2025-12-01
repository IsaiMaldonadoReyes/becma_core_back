<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\core\SistemaController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\core\EmpresaController;

use App\Http\Controllers\comercial\Rpt2VentasPorMarcaController;
use App\Http\Controllers\comercial\RptPresupuestoController;
use App\Http\Controllers\comercial\RptVentasPorConceptoController;

use App\Http\Controllers\nomina\CatalogosController;
use App\Http\Controllers\nomina\DispersionController;
use App\Http\Controllers\nomina\EmpleadoController;

use App\Http\Controllers\nomina\ClienteController;
use App\Http\Controllers\nomina\EmpresaController as NominaEmpresaController;

use App\Http\Controllers\nomina\BancosDispersionController;
use App\Http\Controllers\nomina\ParametrizacionController;
use App\Http\Controllers\nomina\PrenominaController;


use App\Http\Controllers\comercial\KioscoController;
use App\Http\Controllers\nomina\IncidenciaController;

Route::get('/', function () {
    return 'API test becma-core';
});


Route::post('login', [AuthController::class, 'login'])->middleware('web');

Route::post('logout', [AuthController::class, 'logout'])->middleware('web');

//Route::post('register', [AuthController::class, 'register']);




// kiosco

Route::post('empresas', [KioscoController::class, 'empresas']);
Route::post('catalogos', [KioscoController::class, 'catalogos']);
Route::post('listaCodigoPostal', [KioscoController::class, 'listaCodigoPostal']);
Route::post('direccion', [KioscoController::class, 'direccion']);
Route::post('cliente', [KioscoController::class, 'getCliente']);

Route::post('validarTicket', [KioscoController::class, 'validarTicket']);
Route::post('estatusTicket', [KioscoController::class, 'getEstatusTicket']);

Route::post('upsetTicket', [KioscoController::class, 'upsetTicket']);
Route::post('eliminarTicket', [KioscoController::class, 'deleteTicket']);

Route::post('descargarPdf', [KioscoController::class, 'descargarPdf']);
Route::post('descargarXml', [KioscoController::class, 'descargarXml']);



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


    // empresas

    Route::post('rptEmpresas', [EmpresaController::class, 'rptEmpresas']);

    // Rpt2 = Ventas por marcas
    Route::post('labelRpt2', [Rpt2VentasPorMarcaController::class, 'label']);
    Route::post('dataRpt2', [Rpt2VentasPorMarcaController::class, 'dataset']);
    Route::post('marcasRpt2', [Rpt2VentasPorMarcaController::class, 'marcas']);

    // Rpt3 = Ventas por conceptos
    Route::post('conceptosRpt3', [RptVentasPorConceptoController::class, 'conceptosFacturaComercial']);
    Route::post('labelRpt3', [RptVentasPorConceptoController::class, 'label']);
    Route::post('dataRpt3', [RptVentasPorConceptoController::class, 'dataset']);

    // Rpt5 = Presupuestos
    Route::post('ejerciciosRpt5', [RptPresupuestoController::class, 'ejercicios']);
    Route::post('marcasRpt5', [RptPresupuestoController::class, 'marcas']);
    Route::post('agentesRpt5', [RptPresupuestoController::class, 'agentes']);
    Route::post('dataRpt5', [RptPresupuestoController::class, 'dataset']);
    Route::post('dataRpt5Individual', [RptPresupuestoController::class, 'presupuestoIndividual']);


    Route::post('exportExcel', [ExportController::class, 'exportExcel']);

    // sistema
    Route::get('indexSistema', [SistemaController::class, 'index']);
    Route::post('storeSistema', [SistemaController::class, 'store']);
    Route::put('updateSistema/{id}', [SistemaController::class, 'update']);
    Route::delete('/destroySistema/{id}', [SistemaController::class, 'destroy']);
    Route::delete('/destroySistemaByIds', [SistemaController::class, 'destroyByIds']);

    // cliente
    Route::prefix('nominaGapeCliente')->group(function () {
        Route::get('index', [ClienteController::class, 'index']);
        Route::post('store', [ClienteController::class, 'store']);
        Route::put('update/{id}', [ClienteController::class, 'update']);
        Route::delete('/destroy/{id}', [ClienteController::class, 'destroy']);
        Route::delete('/destroyByIds', [ClienteController::class, 'destroyByIds']);
    });


    // Nomina Gape empresa
    Route::prefix('nominaGapeEmpresa')->group(function () {
        Route::get('index', [NominaEmpresaController::class, 'index']);
        Route::post('store', [NominaEmpresaController::class, 'store']);
        Route::put('update/{id}', [NominaEmpresaController::class, 'update']);

        Route::post('sinAsignar', [NominaEmpresaController::class, 'empresasNominasSinAsginar']);
        Route::post('asignadasACliente', [NominaEmpresaController::class, 'asignadasACliente']);
        Route::post('asignadasAClienteTipo', [NominaEmpresaController::class, 'asignadasAClienteTipo']);

        Route::post('datosNominasPorCliente', [NominaEmpresaController::class, 'empresaDatosNominaPorCliente']);
        Route::post('datosNominasPorClienteId', [NominaEmpresaController::class, 'empresaDatosNominaPorClienteId']);
    });


    // Bancos dispersion
    Route::prefix('bancos')->group(function () {
        // General
        Route::get('getBancosByEmpresa/{id}', [BancosDispersionController::class, 'getBancosByEmpresa']);

        // Fondeadora
        Route::post('upsertBancoDispersion', [BancosDispersionController::class, 'upsertBancoDispersion']);

        // Azteca
        Route::post('storeBancoAzteca', [BancosDispersionController::class, 'storeBancoAzteca']);
        Route::put('updateBancoAzteca/{id}', [BancosDispersionController::class, 'updateBancoAzteca']);
        Route::delete('deleteBancoAzteca/{id}', [BancosDispersionController::class, 'deleteBancoAzteca']);

        // Banorte
        Route::post('storeBancoBanorte', [BancosDispersionController::class, 'storeBancoBanorte']);
        Route::put('updateBancoBanorte/{id}', [BancosDispersionController::class, 'updateBancoBanorte']);
        Route::delete('deleteBancoBanorte/{id}', [BancosDispersionController::class, 'deleteBancoBanorte']);
    });


    // Prenomina
    Route::prefix('prenomina')->group(function () {
        // TipoPeriodo nomina_gape_cliente
        Route::post('tipoPeriodo', [CatalogosController::class, 'tipoPeriodoPorClienteEmpresaDisponibles']);

        Route::post('ejerciciosPorTipoPeriodo', [CatalogosController::class, 'ejerciciosPorTipoPeriodoPorClienteEmpresa']);

        Route::post('periodoPorEjercicio', [CatalogosController::class, 'periodoPorEjercicioPorClienteEmpresa']);

        //Route::post('ejecutar', [PrenominaController::class, 'ejecutar']);
        Route::post('fiscal', [PrenominaController::class, 'prenominaFiscal']);
        Route::post('noFiscal', [PrenominaController::class, 'prenominaNoFiscal']);
    });

    Route::prefix('incidencia')->group(function () {
        Route::post('ejerciciosPorTipoPeriodoActivo', [CatalogosController::class, 'ejerciciosPorTipoPeriodoActivo']);

        Route::post('descargaFormatoFiscal', [IncidenciaController::class, 'descargaFormatoFiscal']);
    });

    // Parametrizacion
    Route::prefix('parametrizacion')->group(function () {
        Route::get('index', [ParametrizacionController::class, 'index']);

        Route::post('datosConceptosPorId', [ParametrizacionController::class, 'datosConceptosPorId']);
        Route::post('datosParametrizacionPorId', [ParametrizacionController::class, 'datosParametrizacionPorId']);

        Route::post('upsertConcepto', [ParametrizacionController::class, 'upsertConceptoPagoParametrizacion']);

        Route::post('upsertParametrizacion', [ParametrizacionController::class, 'upsertParametrizacion']);
    });

    // cliente catalogo

    // Empleados
    Route::prefix('nominaGapeEmpleado')->group(function () {
        Route::post('index', [EmpleadoController::class, 'index']);
        Route::post('edit', [EmpleadoController::class, 'edit']);
        Route::post('store', [EmpleadoController::class, 'store']);
        Route::put('update', [EmpleadoController::class, 'update']);

        Route::post('storeNoFiscal', [EmpleadoController::class, 'storeNoFiscal']);
        Route::put('updateNoFiscal', [EmpleadoController::class, 'updateNoFiscal']);

        Route::delete('/destroyEmpleado/{id}', [EmpleadoController::class, 'destroy']);


        // Listar empleados no por empresa cliente
        Route::post('noFiscalesEmpresaCliente', [EmpleadoController::class, 'noFiscalesEmpresaCliente']);

        // Listar empleados por empresa cliente
        Route::post('fiscalesEmpresaCliente', [EmpleadoController::class, 'fiscalesEmpresaCliente']);
    });


    Route::prefix('catalogoNomina')->group(function () {
        // Cliente
        Route::post('gapeCliente', [CatalogosController::class, 'gapeCliente']);

        // TipoPeriodo nomina_gape_cliente
        Route::post('tipoPeriodoNGE', [CatalogosController::class, 'tipoPeriodoNGE']);

        // SATCatTipoContrato
        Route::post('tipoContrato', [CatalogosController::class, 'tipoContrato']);

        // tipoPeriodo
        Route::post('tipoPeriodo', [CatalogosController::class, 'tipoPeriodo']);

        // departamento
        Route::post('departamento', [CatalogosController::class, 'departamento']);

        // puesto
        Route::post('puesto', [CatalogosController::class, 'puesto']);

        // tipoPrestacion
        Route::post('tipoPrestacion', [CatalogosController::class, 'tipoPrestacion']);

        // turno
        Route::post('turno', [CatalogosController::class, 'turno']);

        // tipoRegimen
        Route::post('tipoRegimen', [CatalogosController::class, 'tipoRegimen']);

        // registroPatronal
        Route::post('registroPatronal', [CatalogosController::class, 'registroPatronal']);

        // entidadFederativa
        Route::post('entidadFederativa', [CatalogosController::class, 'entidadFederativa']);

        // banco
        Route::post('bancos', [CatalogosController::class, 'bancos']);

        // tipoJornada
        Route::post('tipoJornada', [CatalogosController::class, 'tipoJornada']);

        // empresa
        Route::post('empresa', [CatalogosController::class, 'empresa']);

        // nominaGapeEmpresa
        Route::post('sigCodigoPorEmpresa', [CatalogosController::class, 'sigCodigoPorEmpresa']);
    });


















    // Listar todas las empresas de nomina

    //* no se usa*/
    Route::post('empresasNominas', [EmpresaController::class, 'empresasNominas']);


    // CATALOGOS NOMINA GAPE



    // Empresa
    Route::post('nominaEmpresaPorCliente', [CatalogosController::class, 'empresa']);

    // SATCatTipoContrato
    Route::post('nominaTipoContrato/{id}', [CatalogosController::class, 'tipoContrato']);

    // TipoPeriodo
    Route::post('nominaTipoPeriodo/{id}', [CatalogosController::class, 'tipoPeriodo']);

    // Periodo
    Route::post('nominaPeriodo/{id}/{idTipoPeriodo}', [CatalogosController::class, 'periodo']);

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


    // sincronizar empresas de nomina con empresa_database
    Route::post('sincronizarEmpresas', [CatalogosController::class, 'sincronizarEmpresasNomGemerales']);

    Route::post('dispersion', [DispersionController::class, 'exportar']);
});
