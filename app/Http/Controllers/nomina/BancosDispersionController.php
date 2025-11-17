<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\nomina\GAPE\NominaGapeBancoAzteca;
use App\Models\nomina\GAPE\NominaGapeBancoBanorte;
use App\Models\nomina\GAPE\NominaGapeBancoDispersion;
use App\Models\nomina\GAPE\NominaGapeBancoFondeadora;

class BancosDispersionController extends Controller
{
    public function getBancosByEmpresa($idEmpresa)
    {
        try {
            // Consultar cada banco
            $bancos = NominaGapeBancoDispersion::where('id_nomina_gape_empresa', $idEmpresa)->first();

            if (!$bancos) {
                $bancos = [
                    'fondeadora' => false,
                    'azteca_interbancario' => false,
                    'azteca_bancario' => false,
                    'banorte' => false,
                ];
            } else {
                // 3️⃣ Convertir correctamente a booleanos
                $bancos = [
                    'fondeadora' => (bool) $bancos->fondeadora,
                    'azteca_interbancario' => (bool) $bancos->azteca_interbancario,
                    'azteca_bancario' => (bool) $bancos->azteca_bancario,
                    'banorte' => (bool) $bancos->banorte,
                ];
            }
            // Azteca - usa el mismo modelo, diferenciando por tipo_banco
            $aztecaInterbancario = NominaGapeBancoAzteca::where('id_nomina_gape_empresa', $idEmpresa)
                ->where('tipo_banco', 'interbancario')
                ->get();

            $aztecaBancario = NominaGapeBancoAzteca::where('id_nomina_gape_empresa', $idEmpresa)
                ->where('tipo_banco', 'bancario')
                ->get();

            $banorte = NominaGapeBancoBanorte::where('id_nomina_gape_empresa', $idEmpresa)->get();

            // Respuesta unificada
            return response()->json([
                'code' => 200,
                'message' => 'Datos de bancos obtenidos correctamente',
                'data' => [
                    'dispersion' => $bancos,
                    'azteca_interbancario' => $aztecaInterbancario,
                    'azteca_bancario' => $aztecaBancario,
                    'banorte' => $banorte,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos de bancos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function upsertBancoDispersion(Request $request)
    {
        try {
            // 1️⃣ Validar siempre el id de empresa
            $validated = $request->validate([
                'id_nomina_gape_empresa' => 'required|integer|exists:nomina_gape_empresa,id',
            ]);

            // 2️⃣ Filtrar solo los campos permitidos que vengan en el request
            $camposPermitidos = ['fondeadora', 'azteca_interbancario', 'azteca_bancario', 'banorte'];
            $data = array_intersect_key($request->all(), array_flip($camposPermitidos));

            // 3️⃣ Validar que los campos opcionales (si vienen) sean booleanos
            foreach ($data as $campo => $valor) {
                if (!is_bool($valor) && !in_array($valor, [0, 1, '0', '1'], true)) {
                    return response()->json([
                        'code' => 422,
                        'message' => "El campo '{$campo}' debe ser booleano (true/false)",
                    ], 422);
                }
                // convertir a boolean real
                $data[$campo] = filter_var($valor, FILTER_VALIDATE_BOOLEAN);
            }

            if (empty($data)) {
                return response()->json([
                    'code' => 400,
                    'message' => 'No se recibieron campos válidos para actualizar',
                ], 400);
            }

            // 4️⃣ Buscar o crear registro
            $registro = NominaGapeBancoDispersion::firstOrNew([
                'id_nomina_gape_empresa' => $validated['id_nomina_gape_empresa'],
            ]);

            // 5️⃣ Actualizar solo los campos enviados
            $registro->fill($data);
            $registro->save();

            // 6️⃣ Responder éxito
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


    public function storeBancoAzteca(Request $request)
    {
        try {
            // 1️⃣ Validar los campos
            $validatedData = $request->validate([
                'id_nomina_gape_empresa' => 'required|integer|exists:nomina_gape_empresa,id',
                'activo_dispersion' => 'required|boolean',
                'cuenta_origen' => 'nullable|string|max:100',
                'tipo_banco' => 'required|string|in:bancario,interbancario',
            ]);

            // 2️⃣ Crear el registro
            NominaGapeBancoAzteca::create($validatedData);

            // 3️⃣ Respuesta exitosa
            return response()->json([
                'code' => 200,
                'message' => 'Registro guardado correctamente',
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
                'message' => 'Se generó un error al guardar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateBancoAzteca(Request $request, $id)
    {
        try {
            // 1️⃣ Validar los campos
            $validatedData = $request->validate([
                'id_nomina_gape_empresa' => 'required|integer|exists:nomina_gape_empresa,id',
                'activo_dispersion' => 'required|boolean',
                'cuenta_origen' => 'nullable|string|max:100',
                'tipo_banco' => 'required|string|in:bancario,interbancario',
            ]);

            // 2️⃣ Buscar el registro por ID de la tabla nomina_gape_banco_azteca
            $bancoAzteca = NominaGapeBancoAzteca::find($id);

            // 3️⃣ Si no existe, devolver error
            if (!$bancoAzteca) {
                return response()->json([
                    'code' => 404,
                    'message' => 'No se encontró el registro especificado',
                ], 404);
            }

            // 4️⃣ Actualizar con los datos validados
            $bancoAzteca->update($validatedData);

            // 5️⃣ Respuesta exitosa
            return response()->json([
                'code' => 200,
                'message' => 'Registro actualizado correctamente',
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
                'message' => 'Se generó un error al actualizar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // 1️⃣ ================= AZTECA =================
    public function deleteBancoAzteca(string $id)
    {
        try {
            // 1️⃣ Validar el ID del registro a eliminar
            $cliente = NominaGapeBancoAzteca::findOrFail($id);
            $cliente->delete();

            // 4️⃣ Responder éxito
            return response()->json([
                'code' => 200,
                'message' => 'Registro eliminado correctamente',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // ⚠️ Error de validación
            return response()->json([
                'code' => 422,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // ⚠️ Error general
            return response()->json([
                'code' => 500,
                'message' => 'Error al eliminar el registro',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    public function storeBancoBanorte(Request $request)
    {
        try {
            // 1️⃣ Validar los campos
            $validatedData = $request->validate([
                'id_nomina_gape_empresa' => 'required|integer|exists:nomina_gape_empresa,id',
                'activo_dispersion' => 'required|boolean',
                'cuenta_origen' => 'nullable|string|max:100',
                'clave_banco' => 'nullable|string|max:50',
            ]);

            // 2️⃣ Crear el registro
            NominaGapeBancoBanorte::create($validatedData);

            // 3️⃣ Responder éxito
            return response()->json([
                'code' => 200,
                'message' => 'Registro guardado correctamente',
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
                'message' => 'Se generó un error al guardar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateBancoBanorte(Request $request, $id)
    {
        try {
            // 1️⃣ Validar los campos
            $validatedData = $request->validate([
                'id_nomina_gape_empresa' => 'required|integer|exists:nomina_gape_empresa,id',
                'activo_dispersion' => 'required|boolean',
                'cuenta_origen' => 'nullable|string|max:100',
                'clave_banco' => 'nullable|string|max:50',
            ]);

            // 2️⃣ Buscar el registro por ID de la tabla nomina_gape_banco_banorte
            $bancoBanorte = NominaGapeBancoBanorte::find($id);

            // 3️⃣ Si no existe, devolver error
            if (!$bancoBanorte) {
                return response()->json([
                    'code' => 404,
                    'message' => 'No se encontró el registro especificado',
                ], 404);
            }

            // 4️⃣ Actualizar con los datos validados
            $bancoBanorte->update($validatedData);

            // 5️⃣ Respuesta exitosa
            return response()->json([
                'code' => 200,
                'message' => 'Registro actualizado correctamente',
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
                'message' => 'Se generó un error al actualizar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // 2️⃣ ================= BANORTE =================
    public function deleteBancoBanorte(string $id)
    {
        try {
            $cliente = NominaGapeBancoBanorte::findOrFail($id);
            // 3️⃣ Eliminar registro
            $cliente->delete();

            // 5️⃣ Respuesta exitosa
            return response()->json([
                'code' => 200,
                'message' => 'Registro eliminado correctamente',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // ⚠️ Error de validación
            return response()->json([
                'code' => 422,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // ⚠️ Error general
            return response()->json([
                'code' => 500,
                'message' => 'Error al eliminar el registro',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
