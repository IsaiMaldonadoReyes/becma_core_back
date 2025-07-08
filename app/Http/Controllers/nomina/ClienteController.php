<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\nomina\gape\cliente\StoreClienteRequest;
use App\Http\Requests\nomina\gape\cliente\UpdateClienteRequest;
use App\Models\nomina\GAPE\NominaGapeCliente;

class ClienteController extends Controller
{
    public function index()
    {
        try {
            $clientes = NominaGapeCliente::select('id', 'nombre', 'codigo', 'telefono', 'estado')
                ->selectRaw("FORMAT(created_at, 'dd-MM-yyyy') as fecha_creacion")
                ->orderBy('nombre', 'asc')
                ->get();

            return response()->json([
                'code' => 200,
                'message' => 'Datos obtenidos correctamente',
                'data' => $clientes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreClienteRequest $request)
    {
        try {
            $validatedData = $request->validated();
            NominaGapeCliente::create($validatedData);

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

    public function update(UpdateClienteRequest $request, string $id)
    {
        try {
            $cliente = NominaGapeCliente::findOrFail($id);
            $cliente->update($request->validated());

            return response()->json([
                'code' => 200,
                'message' => 'Registro actualizado correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Se generó un error al actualizar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $cliente = NominaGapeCliente::findOrFail($id);
            $cliente->delete();

            return response()->json([
                'code' => 200,
                'message' => 'Registro eliminado correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al eliminar el registro',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroyByIds(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer',
            ]);

            NominaGapeCliente::whereIn('id', $request->input('ids'))->delete();

            return response()->json([
                'code' => 200,
                'message' => 'Los registros seleccionados se eliminaron correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al eliminar los registros seleccionados',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
