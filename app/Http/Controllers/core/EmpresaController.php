<?php

namespace App\Http\Controllers\core;

use App\Http\Controllers\Controller;
use App\Models\core\EmpresaUsuario;
use App\Models\core\Conexion;
use App\Models\nomina\GAPE\NominaGapeEmpresa;
use App\Models\nomina\nomGenerales\NominaEmpresa;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

class EmpresaController extends Controller
{

    protected $helperController;

    public function __construct(helperController $helperController)
    {
        $this->helperController = $helperController;
    }

    public function empresasNominas(Request $request)
    {
        // $idEmpresaUsuario = $request->user()->id

        $idEmpresaUsuario = 3;

        try {

            $empresas = EmpresaUsuario::select(
                'empresa_database.id',
                'empresa_database.nombre_empresa',
                'empresa_database.nombre_base'
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
                ->where('empresa_usuario.id', $idEmpresaUsuario)
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $empresas,
            ], 200);
        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function empresaDatosNominaPorCliente(Request $request)
    {
        try {
            $idCliente = $request->idCliente;
            $idEmpresa = $request->idEmpresa;
            $nombreEmpresa = $request->nombreBase; // se recomienda recibir el nombre de la empresa o su ID

            /**
             * 1️⃣ Obtener los datos base de la empresa desde CONTPAQi Nóminas
             */
            $conexion = Conexion::select('id', 'usuario', 'password', 'ip', 'puerto', 'host')->first();

            $this->helperController->setDatabaseConnection($conexion, 'nomGenerales');

            // Buscar SOLO la empresa seleccionada
            $empresaNomina = NominaEmpresa::select(
                'IDEmpresa',
                'NombreEmpresa',
                'NombreCorto',
                'RutaEmpresa',
                DB::raw("rfc + SUBSTRING(CONVERT(char(10),fechaconstitucion , 126), 3,2)
                    + SUBSTRING(CONVERT(char(10),fechaconstitucion , 126), 6,2)
                    + SUBSTRING(CONVERT(char(10),fechaconstitucion, 126), 9,2)
                    + homoclave AS rfc")
            )
                ->where('RutaEmpresa', $nombreEmpresa) // o por IDEmpresa si lo prefieres
                ->first();

            if (!$empresaNomina) {
                return response()->json([
                    'code' => 404,
                    'message' => 'La empresa no fue encontrada en CONTPAQi Nóminas.',
                ]);
            }

            /**
             * 2️⃣ Obtener (si existe) el vínculo en nomina_gape_empresa
             */
            $empresaVinculada = NominaGapeEmpresa::select(
                'razon_social',
                'rfc',
                'codigo_interno',
                'correo_notificacion'
            )
                ->where('id_nomina_gape_cliente', $idCliente)
                ->where('id_empresa_database', $idEmpresa)
                ->where('razon_social', $empresaNomina->NombreEmpresa)
                ->first();

            /**
             * 3️⃣ Combinar los datos (simulando un LEFT JOIN)
             */
            $resultado = [
                'razon_social'        => $empresaNomina->NombreEmpresa,
                'rfc'                 => $empresaNomina->rfc,
                'codigo_interno'      => $empresaVinculada->codigo_interno ?? '',
                'correo_notificacion' => $empresaVinculada->correo_notificacion ?? '',
            ];

            return response()->json([
                'code' => 200,
                'data' => $resultado,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener la información de la empresa',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function empresaDatosNominaPorClienteId(Request $request)
    {
        try {
            $id = $request->id;

            $empresa = NominaGapeEmpresa::select(
                'id_nomina_gape_cliente',
                'id_empresa_database',
                'fiscal',
                'razon_social',
                'rfc',
                'codigo_interno',
                'correo_notificacion'
            )
                ->where('id', $id)
                ->first();

            /**
             * 3️⃣ Combinar los datos (simulando un LEFT JOIN)
             */
            $resultado = [
                'id_nomina_gape_cliente' => $empresa->id_nomina_gape_cliente,
                'id_empresa_database' => $empresa->id_empresa_database ?? 0,
                'razon_social'        => $empresa->razon_social,
                'rfc'                 => $empresa->rfc,
                'codigo_interno'      => $empresa->codigo_interno ?? '',
                'correo_notificacion' => $empresa->correo_notificacion ?? '',
            ];

            return response()->json([
                'code' => 200,
                'data' => $resultado,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener la información de la empresa',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function empresasNominasPorCliente(Request $request)
    {
        $idCliente = $request->idCliente ?? 2; // ejemplo, o puedes obtenerlo del usuario autenticado

        try {
            $empresas = DB::table('empresa_database as ed')
                ->leftJoin('nomina_gape_empresa as nge', 'nge.id_empresa_database', '=', 'ed.id')
                ->leftJoin('conexion as con', 'ed.id_conexion', '=', 'con.id')
                ->leftJoin('sistema as sis', 'con.id_sistema', '=', 'sis.id')
                ->select('ed.id', 'ed.nombre_empresa', 'ed.nombre_base')
                ->where('ed.estado', 1)
                ->where('sis.codigo', 'Nom')
                ->whereNull('nge.id_nomina_gape_cliente') // 👈 solo las NO relacionadas
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $empresas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener las empresas disponibles',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /*
    public function empresasNominasPorCliente(Request $request)
    {
        $idCliente = $request->idCliente ?? 2; // ejemplo, o puedes obtenerlo del usuario autenticado

        try {
            $empresas = DB::table('empresa_database as ed')
                ->leftJoin('nomina_gape_empresa as nge', 'nge.id_empresa_database', '=', 'ed.id')
                ->leftJoin('conexion as con', 'ed.id_conexion', '=', 'con.id')
                ->leftJoin('sistema as sis', 'con.id_sistema', '=', 'sis.id')
                ->select('ed.id', 'ed.nombre_empresa', 'ed.nombre_base')
                ->where('ed.estado', 1)
                ->where('sis.codigo', 'Nom')
                ->where(function ($query) use ($idCliente) {
                    $query->whereNull('nge.id_nomina_gape_cliente')
                        ->orWhere('nge.id_nomina_gape_cliente', $idCliente);
                })
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $empresas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener las empresas disponibles',
                'error' => $e->getMessage(),
            ]);
        }
    }
*/

    public function rptEmpresas(Request $request)
    {

        // obtener empresas de sistema comercial
        $idEmpresaUsuario = 5;

        try {
            $empresas = EmpresaUsuario::select(
                'empresa_database.id',
                'empresa_database.nombre_empresa',
                'empresa_database.nombre_base'
            )
                ->join('empresa_usuario_database', 'empresa_usuario.id', '=', 'empresa_usuario_database.id_empresa_usuario')
                ->join('empresa_database', 'empresa_usuario_database.id_empresa_database', '=', 'empresa_database.id')
                ->join('core_usuario_conexion', 'empresa_database.id_conexion', '=', 'core_usuario_conexion.id_conexion')
                ->join('conexion', 'empresa_database.id_conexion', '=', 'conexion.id')
                ->join('sistema', 'conexion.id_sistema', '=', 'sistema.id')
                ->where('core_usuario_conexion.estado', 1)
                ->where('empresa_database.estado', 1)
                ->where('sistema.codigo', '=', 'Comercial')
                ->where('empresa_usuario_database.estado', 1)
                ->where('empresa_usuario.id', $idEmpresaUsuario)
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $empresas,
            ], 200);
        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
