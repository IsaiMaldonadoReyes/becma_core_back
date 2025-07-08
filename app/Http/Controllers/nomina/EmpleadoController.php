<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use App\Http\Requests\nomina\gape\StoreEmpleadoRequest;
use Illuminate\Http\Request;

// Importar modelos necesarios
use App\Models\nomina\GAPE\NominaGapeEmpleado; // Asegúrate de que este modelo exista

class EmpleadoController extends Controller
{
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
    public function store(StoreEmpleadoRequest $request)
    {
        //
        try {
            $validateData = $request->validated();

            dd($validateData);

            $empleado = NominaGapeEmpleado::create($validateData);
            // Aquí puedes agregar lógica adicional después de crear el empleado

            return response()->json([
                'code' => 200,
                'message' => 'Empleado creado correctamente',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Se generó un error al momento de guardar',
                'error' => $e->getMessage()
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
