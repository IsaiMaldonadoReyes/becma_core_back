<?php

namespace App\Http\Controllers\core;

use App\Http\Controllers\Controller;
use App\Http\Requests\core\StoreSistemaRequest;
use App\Http\Requests\core\UpdateSistemaRequest;
use Illuminate\Http\Request;

use App\Models\core\Sistema;

class SistemaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Obtener todos los registros de la tabla sistemas con los campos específicos
            $sistemas = Sistema::select('id', 'nombre', 'codigo', 'descripcion', 'estado')
                ->selectRaw("FORMAT(created_at, 'dd-MM-yyyy') as fecha_creacion")
                //->where('estado', true)
                ->orderBy('nombre', 'asc')
                ->get();

            // Retornar una respuesta JSON con los datos
            return response()->json([
                'code' => 200,
                'message' => 'Datos obtenidos correctamente',
                'data' => $sistemas,
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
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSistemaRequest $request)
    {
        try {

            $validateData = $request->validated();

            //$validateData['estado'] = 1;

            $sistema = Sistema::create($validateData);

            return response()->json([
                'code' => 200,
                'message' => 'Registro guardado correctamente'
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
    public function update(UpdateSistemaRequest $request, string $id)
    {
        try {
            // Buscar el sistema por ID
            $sistema = Sistema::findOrFail($id);

            // Actualizar el sistema con los datos validados
            $sistema->update($request->validated());

            return response()->json([
                'code' => 200,
                'message' => 'Registro actualizado correctamente',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Se generó un error al momento de actualizar',
                'error' => $e->getMessage(), // Opcional: para depuración
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $sistema = Sistema::findOrFail($id);
            $sistema->delete();

            return response()->json([
                'code' => 200,
                'message' => 'Registro eliminado correctamente',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al eliminar el registro',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
