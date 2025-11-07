<?php

namespace App\Http\Controllers\nomina;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;
use App\Http\Controllers\core\HelperController;

use App\Http\Requests\nomina\gape\empresa\StoreEmpresaRequest;
use App\Http\Requests\nomina\gape\empresa\UpdateEmpresaRequest;

use App\Models\core\Conexion;
use App\Models\nomina\GAPE\NominaGapeEmpresa;
use App\Models\nomina\nomGenerales\NominaEmpresa;
use App\Models\core\EmpresaDatabase;


class EmpresaController extends Controller
{
    protected $helperController;

    public function __construct(helperController $helperController)
    {
        $this->helperController = $helperController;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $empresas = DB::table('nomina_gape_empresa as nge')
                ->leftJoin('nomina_gape_cliente as ngc', 'nge.id_nomina_gape_cliente', '=', 'ngc.id')
                ->leftJoin('empresa_database as ed', 'nge.id_empresa_database', '=', 'ed.id')
                ->select(
                    'nge.id as id',
                    'ngc.nombre as cliente',
                    DB::raw("ISNULL(ed.nombre_empresa, 'Sin empresa fiscal') as empresa"),
                    DB::raw("CASE WHEN nge.fiscal = 0 THEN 'No fiscal' ELSE 'Fiscal' END as tipo"),
                    'nge.razon_social',
                    'nge.rfc',
                    'nge.codigo_interno',
                    DB::raw("FORMAT(nge.created_at, 'dd-MM-yyyy') as fecha_creacion")
                )
                ->orderBy('ngc.nombre')
                ->orderBy('tipo')
                ->orderBy('fecha_creacion')
                ->get();

            return response()->json([
                'code' => 200,
                'message' => 'Datos obtenidos correctamente',
                'data' => $empresas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos de las empresas',
                'error' => $e->getMessage(),
            ], 500);
        }
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
    public function store(StoreEmpresaRequest $request)
    {
        try {
            $validatedData = $request->validated();
            $empresa = NominaGapeEmpresa::create($validatedData);

            return response()->json([
                'code' => 200,
                'message' => 'Registro guardado correctamente',
                'id' => $empresa->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Se generó un error al guardar',
                'error' => $e->getMessage(),
            ], 500);
        }
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
    public function update(UpdateEmpresaRequest $request, string $id)
    {
        //
        try {
            $empresa = NominaGapeEmpresa::findOrFail($id);
            $empresa->update($request->validated());

            return response()->json([
                'code' => 200,
                'message' => 'Registro guardado correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Se generó un error al guardar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function empresasNominasSinAsginar()
    {
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

    //** Sirve para traer las empresas de nóminas por el ID del cliente y saber si es fiscal y no fiscal */
    public function empresasNominasPorClienteTipo(Request $request)
    {
        $idCliente = $request->idCliente; // ejemplo, o puedes obtenerlo del usuario autenticado
        $fiscal = $request->fiscal; // ejemplo, o puedes obtenerlo del usuario autenticado

        try {
            $empresas = DB::table('empresa_database as ed')
                ->leftJoin('nomina_gape_empresa as nge', 'nge.id_empresa_database', '=', 'ed.id')
                ->leftJoin('conexion as con', 'ed.id_conexion', '=', 'con.id')
                ->leftJoin('sistema as sis', 'con.id_sistema', '=', 'sis.id')
                ->select('ed.id', 'ed.nombre_empresa', 'ed.nombre_base')
                ->where('ed.estado', 1)
                ->where('sis.codigo', 'Nom')
                ->where('nge.id_nomina_gape_cliente', $idCliente) // 👈 solo las NO relacionadas
                ->where('nge.fiscal', $fiscal) // 👈 solo las NO relacionadas
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

    public function asignadasACliente(Request $request)
    {
        $idCliente = $request->idCliente; // ejemplo, o puedes obtenerlo del usuario autenticado

        try {
            $empresas = EmpresaDatabase::query()
                ->leftJoin('nomina_gape_empresa as nge', 'nge.id_empresa_database', '=', 'empresa_database.id')
                ->leftJoin('conexion as con', 'empresa_database.id_conexion', '=', 'con.id')
                ->leftJoin('sistema as sis', 'con.id_sistema', '=', 'sis.id')
                ->select('empresa_database.id', 'empresa_database.nombre_empresa', 'empresa_database.nombre_base')
                ->where('empresa_database.estado', 1)
                ->where('sis.codigo', 'Nom')
                ->where('nge.id_nomina_gape_cliente', $idCliente)
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

    public function asignadasAClienteTipo(Request $request)
    {
        $idCliente = $request->idCliente; // ejemplo, o puedes obtenerlo del usuario autenticado
        $fiscal = $request->fiscal; // fiscal no fiscal

        try {
            $empresas = NominaGapeEmpresa::query()
                ->select('id', 'id_empresa_database', 'razon_social', 'rfc')
                ->where('id_nomina_gape_cliente', $idCliente)
                ->where('fiscal', $fiscal)
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
                'correo_notificacion',
                'fiscal',
                'estado'
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
                'fiscal' => $empresa->fiscal,
                'estado' => $empresa->estado
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
}
