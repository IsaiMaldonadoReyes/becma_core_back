<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\nomina\GAPE\NominaGapeBancoAzteca;
use App\Models\nomina\GAPE\NominaGapeBancoBanorte;
use App\Models\nomina\GAPE\NominaGapeBancoFondeadora;

class BancosDispersionController extends Controller
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

    public function getBancosByEmpresa($idEmpresa)
    {
        try {
            // Consultar cada banco
            $fondeadora = NominaGapeBancoFondeadora::where('id_nomina_gape_empresa', $idEmpresa)->first();

            // Azteca - usa el mismo modelo, diferenciando por tipo_banco
            $aztecaInterbancario = NominaGapeBancoAzteca::where('id_nomina_gape_empresa', $idEmpresa)
                ->where('tipo_banco', 'interbancario')
                ->first();

            $aztecaBancario = NominaGapeBancoAzteca::where('id_nomina_gape_empresa', $idEmpresa)
                ->where('tipo_banco', 'bancario')
                ->first();

            $banorte = NominaGapeBancoBanorte::where('id_nomina_gape_empresa', $idEmpresa)->first();

            // Respuesta unificada
            return response()->json([
                'code' => 200,
                'message' => 'Datos de bancos obtenidos correctamente',
                'data' => [
                    'fondeadora' => $fondeadora,
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


    public function upsertBancoFondeadora(Request $request)
    {
        try {
            // 1️⃣ Validar los datos
            $validatedData = $request->validate([
                'id_nomina_gape_empresa' => 'required|integer|exists:nomina_gape_empresa,id',
                'activo_dispersion' => 'required|boolean',
            ]);

            // 2️⃣ Usar updateOrCreate: busca por id_nomina_gape_empresa
            $bancoFondeadora = NominaGapeBancoFondeadora::updateOrCreate(
                ['id_nomina_gape_empresa' => $validatedData['id_nomina_gape_empresa']], // criterios de búsqueda
                ['activo_dispersion' => $validatedData['activo_dispersion']] // valores a actualizar
            );

            // 3️⃣ Responder según si fue creado o actualizado
            $message = $bancoFondeadora->wasRecentlyCreated
                ? 'Registro creado correctamente'
                : 'Registro actualizado correctamente';

            return response()->json([
                'code' => 200,
                'message' => $message,
                'data' => $bancoFondeadora, // 👈 opcional: devolvemos el registro
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
                'message' => 'Se generó un error al guardar o actualizar',
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
}
