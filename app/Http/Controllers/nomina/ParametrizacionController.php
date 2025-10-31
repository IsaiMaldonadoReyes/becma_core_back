<?php

namespace App\Http\Controllers\nomina;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;

use App\Models\nomina\GAPE\NominaGapeConceptoPagoParametrizacion;
use App\Models\nomina\GAPE\NominaGapeParametrizacion;

class ParametrizacionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        try {
            $empresas = DB::table('nomina_gape_empresa as nge')
                ->join('nomina_gape_cliente as ngc', 'nge.id_nomina_gape_cliente', '=', 'ngc.id')
                ->leftJoin('empresa_database as ed', 'nge.id_empresa_database', '=', 'ed.id')
                ->join('nomina_gape_concepto_pago_parametrizacion as ngcpp', 'nge.id', '=', 'ngcpp.id_nomina_gape_empresa')
                ->select(
                    'ngcpp.id as id',
                    'ngc.nombre as cliente',
                    DB::raw("ISNULL(ed.nombre_empresa, 'Sin empresa fiscal') as empresa"),
                    DB::raw("CASE WHEN nge.fiscal = 0 THEN 'No fiscal' ELSE 'Fiscal' END as tipo"),
                    DB::raw("ISNULL(ngcpp.tipo_periodo_nombre, 'Sin periodo') as periodo"),
                    'nge.razon_social',
                    'nge.rfc',
                    'nge.codigo_interno',
                    DB::raw("FORMAT(ngcpp.created_at, 'dd-MM-yyyy') as fecha_creacion")
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
    public function datosConceptosPorId(Request $request)
    {
        try {
            $id = $request->id;

            $parametrizacionConcepto = NominaGapeConceptoPagoParametrizacion::select(
                'id',
                'id_nomina_gape_cliente',
                'id_nomina_gape_empresa',
                'id_tipo_periodo',
                'tipo_periodo_nombre',

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

                'estado'
            )
                ->where('id', $id)
                ->first();

            return response()->json([
                'code' => 200,
                'data' => $parametrizacionConcepto,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener la información de la empresa',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function datosParametrizacionPorId(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->idNominaGapeCliente;
            $idNominaGapeEmpresa = $request->idNominaGapeEmpresa;
            $idTipoPeriodo = $request->idTipoPeriodo;

            $query = NominaGapeParametrizacion::select(
                'id',
                'estado',
                'id_nomina_gape_cliente',
                'id_nomina_gape_empresa',
                'id_tipo_periodo',
                'tipo_periodo_nombre',
                'clase_prima_riesgo',
                'clase_prima_riesgo_valor',
                'fee',
                'base_fee',
                'provisiones',
                'isn',
                'cuota_sindical'
            )
                ->where('id_nomina_gape_cliente', $idNominaGapeCliente)
                ->where('id_nomina_gape_empresa', $idNominaGapeEmpresa);

            // 🔹 Si el idTipoPeriodo viene definido y no es nulo, lo incluimos en el filtro
            if (!empty($idTipoPeriodo)) {
                $query->where('id_tipo_periodo', $idTipoPeriodo);
            }

            $parametrizacionConcepto = $query->first();

            return response()->json([
                'code' => 200,
                'data' => $parametrizacionConcepto,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener la información de la parametrización',
                'error' => $e->getMessage(),
            ]);
        }
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

    public function upsertParametrizacion(Request $request)
    {
        try {
            // 1️⃣ Validar los campos obligatorios
            $validated = $request->validate([
                'id_nomina_gape_cliente' => 'required|integer|exists:nomina_gape_cliente,id',
                'id_nomina_gape_empresa' => 'required|integer|exists:nomina_gape_empresa,id',
            ]);

            // 2️⃣ Filtrar solo los campos válidos
            $camposPermitidos = [
                'estado',
                'id_nomina_gape_cliente',
                'id_nomina_gape_empresa',
                'id_tipo_periodo',
                'tipo_periodo_nombre', // ✅ Campo string
                'clase_prima_riesgo',
                'clase_prima_riesgo_valor',
                'fee',
                'base_fee',
                'provisiones',
                'isn',
                'cuota_sindical',
            ];

            $data = array_intersect_key($request->all(), array_flip($camposPermitidos));

            // 3️⃣ Clasificar tipos por categoría
            $camposFloat = ['fee', 'isn', 'clase_prima_riesgo_valor'];
            $camposEnteros = ['id_tipo_periodo'];
            $camposString = ['tipo_periodo_nombre', 'clase_prima_riesgo', 'base_fee', 'provisiones', 'cuota_sindical'];

            // 4️⃣ Normalizar tipos de datos
            foreach ($data as $campo => $valor) {
                if (in_array($campo, $camposFloat)) {
                    $data[$campo] = is_null($valor) ? null : (float) $valor;
                } elseif (in_array($campo, $camposEnteros)) {
                    $data[$campo] = is_null($valor) ? null : (int) $valor;
                } elseif (in_array($campo, $camposString)) {
                    $data[$campo] = is_null($valor) ? null : trim((string) $valor);
                } elseif ($campo === 'estado') {
                    $data[$campo] = filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
            }

            // 5️⃣ Construir condición dinámica
            $condicion = [
                'id_nomina_gape_cliente' => $validated['id_nomina_gape_cliente'],
                'id_nomina_gape_empresa' => $validated['id_nomina_gape_empresa'],
            ];

            // Si se manda el tipo de periodo, también se incluye en la búsqueda
            if ($request->filled('id_tipo_periodo') && (int) $request->input('id_tipo_periodo') !== 0) {
                $condicion['id_tipo_periodo'] = (int) $request->input('id_tipo_periodo');
            }

            // 6️⃣ Buscar si ya existe el registro (considerando id_tipo_periodo si aplica)
            $registro = NominaGapeParametrizacion::firstOrNew($condicion);

            // 7️⃣ Actualizar o crear
            $registro->fill($data);
            $registro->save();

            // 8️⃣ Respuesta uniforme
            return response()->json([
                'code' => 200,
                'message' => $registro->wasRecentlyCreated
                    ? 'Registro creado correctamente'
                    : 'Registro actualizado correctamente',
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
                'message' => 'Error al guardar o actualizar la parametrización',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function upsertConceptoPagoParametrizacion(Request $request)
    {
        try {
            // 1️⃣ Validar los campos obligatorios mínimos
            $validated = $request->validate([
                'id_nomina_gape_cliente' => 'required|integer|exists:nomina_gape_cliente,id',
                'id_nomina_gape_empresa' => 'required|integer|exists:nomina_gape_empresa,id',
            ]);

            // 2️⃣ Campos permitidos según la tabla
            $camposPermitidos = [
                'id_tipo_periodo',
                'tipo_periodo_nombre',
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
                'estado',
            ];

            // 3️⃣ Filtrar solo los campos válidos que vengan en el request
            $data = array_intersect_key($request->all(), array_flip($camposPermitidos));

            // 4️⃣ Clasificar tipos (para normalización)
            $camposEnteros = ['id_tipo_periodo'];
            $camposFloat = [
                'sueldo_imss_tope',
                'prev_social_tope',
                'fondos_sind_tope',
                'tarjeta_facil_tope',
                'hon_asimilados_tope',
                'gastos_compro_tope',
            ];
            $camposOrden = [
                'sueldo_imss_orden',
                'prev_social_orden',
                'fondos_sind_orden',
                'tarjeta_facil_orden',
                'hon_asimilados_orden',
                'gastos_compro_orden',
            ];
            $camposString = ['tipo_periodo_nombre'];
            $camposBooleanos = [
                'sueldo_imss',
                'prev_social',
                'fondos_sind',
                'tarjeta_facil',
                'hon_asimilados',
                'gastos_compro',
                'estado', // 👈 agregado explícitamente
            ];

            // 5️⃣ Normalizar tipos de datos según su categoría
            foreach ($data as $campo => $valor) {
                if (in_array($campo, $camposFloat)) {
                    $data[$campo] = is_null($valor) ? null : (float) $valor;
                } elseif (in_array($campo, $camposOrden) || in_array($campo, $camposEnteros)) {
                    $data[$campo] = is_null($valor) ? null : (int) $valor;
                } elseif (in_array($campo, $camposString)) {
                    $data[$campo] = is_null($valor) ? null : trim((string) $valor);
                } elseif (in_array($campo, $camposBooleanos)) {
                    // ✅ Validación explícita para booleanos
                    if (!is_bool($valor) && !in_array($valor, [0, 1, '0', '1'], true)) {
                        return response()->json([
                            'code' => 422,
                            'message' => "El campo '{$campo}' debe ser booleano (true/false)",
                        ], 422);
                    }
                    $data[$campo] = filter_var($valor, FILTER_VALIDATE_BOOLEAN);
                }
            }

            // 6️⃣ Validar si hay datos para guardar
            if (empty($data)) {
                return response()->json([
                    'code' => 400,
                    'message' => 'No se recibieron campos válidos para actualizar',
                ], 400);
            }

            // 7️⃣ Construir la condición de búsqueda dinámica
            $condicion = [
                'id_nomina_gape_cliente' => $validated['id_nomina_gape_cliente'],
                'id_nomina_gape_empresa' => $validated['id_nomina_gape_empresa'],
            ];

            // Si el request trae id_tipo_periodo, agregarlo a la búsqueda
            if ($request->filled('id_tipo_periodo') && (int) $request->input('id_tipo_periodo') !== 0) {
                $condicion['id_tipo_periodo'] = (int) $request->input('id_tipo_periodo');
            }

            // 8️⃣ Buscar o crear el registro
            $registro = NominaGapeConceptoPagoParametrizacion::firstOrNew($condicion);

            // 9️⃣ Llenar y guardar los datos
            $registro->fill($data);
            $registro->save();

            // 🔟 Mensaje dinámico
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
