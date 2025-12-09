<?php

namespace App\Http\Services\Core;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

use App\Models\core\EmpresaUsuario;
use App\Models\core\EmpresaDatabase;
use App\Models\nomina\GAPE\NominaGapeEmpresa;

class HelperService
{
    public function setDatabaseConnection($datosEmpresa, $nombre_base)
    {

        if (!$datosEmpresa) {
            throw new \Exception("Datos de empresa inválidos o falta 'base_datos'");
        }

        Config::set('database.connections.sqlsrv_dynamic', [
            'driver' => 'sqlsrv',
            'database' => $nombre_base,
            'host' => "$datosEmpresa->ip\\$datosEmpresa->host",
            'port' => $datosEmpresa->puerto,
            'username' => $datosEmpresa->usuario,
            'password' => $datosEmpresa->password,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ]);


        DB::purge('sqlsrv_dynamic');
        DB::reconnect('sqlsrv_dynamic');
    }

    public function getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, $codigoSistema)
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
                ->where('sistema.codigo', '=', $codigoSistema)
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

    public function getConexionDatabaseNGE($idNominaGapeEmpresa, $codigoSistema)
    {
        if (!isset($idNominaGapeEmpresa)) {
            throw new \Exception("Falta el parámetro 'idEmpresaDatabase'");
        }

        try {
            $conexion = EmpresaDatabase::select(
                'conexion.id',
                'empresa_database.nombre_empresa',
                'empresa_database.nombre_base',
                'conexion.usuario',
                'conexion.password',
                'conexion.ip',
                'conexion.puerto',
                'sistema.database_maestra'
            )
                ->join('conexion', 'empresa_database.id_conexion', '=', 'conexion.id')
                ->join('sistema', 'conexion.id_sistema', '=', 'sistema.id')
                ->join('nomina_gape_empresa', 'empresa_database.id', '=', 'nomina_gape_empresa.id_empresa_database')
                ->where('nomina_gape_empresa.id', $idNominaGapeEmpresa)
                ->where('sistema.codigo', '=', $codigoSistema)
                ->first();

            if (!$conexion) {
                throw new \Exception("No se encontró conexión válida para la base con ID {$idNominaGapeEmpresa}");
            }

            return $conexion;
        } catch (\Exception $e) {
            throw new \Exception("Error al obtener la conexión: " . $e->getMessage());
        }
    }


    public function resetToDefaultDatabase()
    {
        $default = config('database.default');
        $defaultConfig = config("database.connections.$default");

        if (!$defaultConfig) {
            throw new \Exception("No se encontró la configuración para la conexión por defecto.");
        }

        // Restaurar la conexión por defecto en tiempo de ejecución
        Config::set('database.default', $default);
        DB::purge($default); // Limpia cualquier conexión activa
        DB::reconnect($default); // Reconecta con la base de datos original

        return true;
    }
}
