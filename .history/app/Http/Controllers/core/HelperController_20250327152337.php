<?php

namespace App\Http\Controllers\core;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class HelperController extends Controller
{
    public function setDatabaseConnection($datosEmpresa)
    {

        if (!$datosEmpresa || !isset($datosEmpresa->base_datos)) {
            throw new \Exception("Datos de empresa inválidos o falta 'base_datos'");
        }

        Config::set(['database.default' => 'sqlsrv']);
        Config::set(['database.connections.sqlsrv' => [
            'driver' => 'sqlsrv',
            'database' => $datosEmpresa->nombre_base,
            'host' => config('database.connections.sqlsrv.host'),
            'port' => config('database.connections.sqlsrv.port'),
            'username' => config('database.connections.sqlsrv.username'),
            'password' => config('database.connections.sqlsrv.password'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ]]);


        DB::reconnect('sqlsrv');
    }
}
