<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\nomina\GAPE\NominaGapeConceptoPagoParametrizacion;

class ParametrizacionController extends Controller
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

    public function upsertConceptoPagoParametrizacion(Request $request)
    {
        try {
            // 1️⃣ Validar los campos obligatorios
            $validated = $request->validate([
                'id_nomina_gape_cliente' => 'required|integer|exists:nomina_gape_cliente,id',
                'id_nomina_gape_empresa' => 'required|integer|exists:nomina_gape_empresa,id',
                'id_tipo_periodo' => 'required|integer',
            ]);

            // 2️⃣ Campos opcionales permitidos (según la estructura de tu tabla)
            $camposPermitidos = [
                'sueldo_imss',
                'sueldo_imss_tope',
                'sueldo_imss_orden',
                'prev_social',
                'prev_social_tope',
                'prev_social_orden',
                'fondos_sind',
                'fondos_sind_tope',
                'fondos_sind_orden',
                'tarjeta_facil',
                'tarjeta_facil_tope',
                'tarjeta_facil_orden',
                'hon_asimilados',
                'hon_asimilados_tope',
                'hon_asimilados_orden',
                'gastos_compro',
                'gastos_compro_tope',
                'gastos_compro_orden',
            ];

            // 3️⃣ Filtrar solo los campos que vengan en el request
            $data = array_intersect_key($request->all(), array_flip($camposPermitidos));

            // 4️⃣ Normalizar los tipos (booleans, floats e ints)
            foreach ($data as $campo => $valor) {
                if (str_ends_with($campo, '_tope')) {
                    // Campos *_tope → float
                    $data[$campo] = is_null($valor) ? null : (float) $valor;
                } elseif (str_ends_with($campo, '_orden')) {
                    // Campos *_orden → int
                    $data[$campo] = is_null($valor) ? null : (int) $valor;
                } else {
                    // Campos principales → booleanos
                    if (!is_bool($valor) && !in_array($valor, [0, 1, '0', '1'], true)) {
                        return response()->json([
                            'code' => 422,
                            'message' => "El campo '{$campo}' debe ser booleano (true/false)",
                        ], 422);
                    }
                    $data[$campo] = filter_var($valor, FILTER_VALIDATE_BOOLEAN);
                }
            }

            if (empty($data)) {
                return response()->json([
                    'code' => 400,
                    'message' => 'No se recibieron campos válidos para actualizar',
                ], 400);
            }

            // 5️⃣ Buscar registro existente o crear uno nuevo
            $registro = NominaGapeConceptoPagoParametrizacion::firstOrNew([
                'id_nomina_gape_cliente' => $validated['id_nomina_gape_cliente'],
                'id_nomina_gape_empresa' => $validated['id_nomina_gape_empresa'],
                'id_tipo_periodo' => $validated['id_tipo_periodo'],
            ]);

            // 6️⃣ Actualizar solo los campos enviados
            $registro->fill($data);
            $registro->save();

            // 7️⃣ Mensaje dinámico
            $message = $registro->wasRecentlyCreated
                ? 'Registro creado correctamente'
                : 'Registro actualizado correctamente';

            return response()->json([
                'code' => 200,
                'message' => $message,
                'data' => $registro,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al guardar o actualizar el registro',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
