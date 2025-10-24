<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\nomina\gape\empresa\StoreEmpresaRequest;
use App\Http\Requests\nomina\gape\empresa\UpdateEmpresaRequest;
use App\Models\nomina\GAPE\NominaGapeEmpresa;

use Illuminate\Support\Facades\DB;

class EmpresaController extends Controller
{
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
}
