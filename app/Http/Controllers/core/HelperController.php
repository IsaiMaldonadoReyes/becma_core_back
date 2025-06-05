<?php

namespace App\Http\Controllers\core;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

use App\Models\core\EmpresaUsuario;

class HelperController extends Controller
{

    public function setDatabaseConnection($datosEmpresa, $nombre_base)
    {

        if (!$datosEmpresa) {
            throw new \Exception("Datos de empresa inválidos o falta 'base_datos'");
        }

        Config::set(['database.default' => 'sqlsrv']);
        Config::set(['database.connections.sqlsrv' => [
            'driver' => 'sqlsrv',
            'database' => $nombre_base,
            'host' => "$datosEmpresa->ip\\$datosEmpresa->host",
            'port' => $datosEmpresa->puerto,
            'username' => $datosEmpresa->usuario,
            'password' => $datosEmpresa->password,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ]]);


        DB::reconnect('sqlsrv');
    }

    public function setDefaultConnection($datosEmpresa)
    {
        if (!$datosEmpresa || !isset($datosEmpresa->nombre_base)) {
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

    public function getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario)
    {

        if (!isset($idEmpresaDatabase) && !isset($idEmpresaUsuario)) {
            throw new \Exception("Datos de empresa inválidos o falta 'base_datos'");
        }

        try {
            $conexion = EmpresaUsuario::select(
                'empresa_database.id',
                'empresa_database.nombre_empresa',
                'empresa_database.nombre_base',
                'conexion.usuario',
                'conexion.password',
                'conexion.ip',
                'conexion.puerto',
                'sistema.database_maestra',
            )
                ->join('empresa_usuario_database', 'empresa_usuario.id', '=', 'empresa_usuario_database.id_empresa_usuario')
                ->join('empresa_database', 'empresa_usuario_database.id_empresa_database', '=', 'empresa_database.id')
                ->join('core_usuario_conexion', 'empresa_database.id_conexion', '=', 'core_usuario_conexion.id_conexion')
                ->join('conexion', 'empresa_database.id_conexion', '=', 'conexion.id')
                ->join('sistema', 'conexion.id_sistema', '=', 'sistema.id')
                ->where('core_usuario_conexion.estado', 1)
                ->where('empresa_database.estado', 1)
                ->where('empresa_usuario_database.estado', 1)
                ->where('sistema.codigo', '=', 'Nom')
                ->where('sistema.estado', 1)
                ->where('empresa_database.id', $idEmpresaDatabase)
                ->where('empresa_usuario.id', $idEmpresaUsuario)
                ->first();
            return $conexion;
        } catch (\Exception $e) {
            // Manejo de errores
            throw new \Exception("Error al obtener la conexión: " . $e->getMessage());
        }
    }
}
