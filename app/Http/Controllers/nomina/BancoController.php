<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\nomina\GAPE\NominaGapeBanco;

class BancoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function bancos()
    {
        try {
            $bancos = NominaGapeBanco::select('id', 'banco', 'clave_banco', 'clave_interna', 'requiere_cuenta_origen', 'estado')
                ->get();

            return response()->json([
                'code' => 200,
                'message' => 'Datos obtenidos correctamente',
                'data' => $bancos,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos',
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
