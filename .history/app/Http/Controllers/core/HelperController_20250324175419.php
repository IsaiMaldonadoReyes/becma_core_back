<?php

namespace App\Http\Controllers\core;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class HelperController extends Controller
{
    public function setDatabaseConnection($datosEmpresa)
    {
        Config::set(['database.default' => 'sqlsrv']);
        Config::set(['database.connections.sqlsrv' => [
            'driver' => 'sqlsrv',
            'database' => $datosEmpresa->base_datos,
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
