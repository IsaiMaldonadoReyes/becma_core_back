<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;

use ZipArchive;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

use App\Models\nomina\GAPE\NominaGapeBancoAzteca;
use App\Models\nomina\GAPE\NominaGapeBancoBanorte;
use App\Models\nomina\GAPE\NominaGapeBancoDispersion;
use App\Models\nomina\GAPE\NominaGapeBancoFondeadora;

use App\Models\nomina\GAPE\NominaGapeBancoConfiguracionEsquema;
use App\Models\nomina\GAPE\NominaGapeBancoBanco;

use App\Models\nomina\default\Empresa;
// refactor

use Maatwebsite\Excel\Facades\Excel;

use App\Exports\FondeadoraExport;
use App\Exports\AztecaBancarioExport;
use App\Exports\AztecaInterbancarioExport;
use App\Exports\BanorteTerceroExport;
use App\Exports\BanorteInterbancarioExport;

use App\Http\Services\Core\HelperService;

use App\Http\Services\Nomina\Export\Dispersion\ConfigDispersionService;
use App\Http\Services\Nomina\Export\Dispersion\QueryService;


class BancosDispersionController extends Controller
{
    protected array $hojasFiscal = [
        'SUELDO_IMSS',
        'ASIMILADOS'
    ];

    private function getColumnasPorEsquema(array $indicesDetalle): array
    {
        $columnasFijas = [
            'codigoempleado',
            'nombre',
            'rfc',
            'claveBanco',
            'cuentaPagoElectronico',
            'clabeInterbancaria',
            'campoextra3',
            'tarjetafacil',
        ];

        $resultado = [];

        foreach ($indicesDetalle as $col) {
            if (!in_array(strtolower($col), $columnasFijas)) {
                $resultado[] = $col; // ← son esquemas
            }
        }

        return $resultado; // ['Sueldo IMSS', 'Sindicato']
    }

    private function filtrarDetallePorEsquema(array $dataDetalle, string $esquema): array
    {
        $resultado = [];

        foreach ($dataDetalle as $row) {
            if (!array_key_exists($esquema, $row)) {
                continue;
            }

            $resultado[] = [
                'codigoempleado'            => $row['codigoempleado'],
                'nombre'                    => $row['nombre'],
                'rfc'                       => $row['rfc'],
                'claveBanco'                => $row['claveBanco'],
                'cuentaPagoElectronico'     => $row['cuentaPagoElectronico'],
                'clabeInterbancaria'        => $row['clabeInterbancaria'],
                'campoextra3'               => $row['campoextra3'],
                'tarjetafacil'              => $row['tarjetafacil'],
                'importe'                   => $row[$esquema],
            ];
        }

        return $resultado;
    }

    public function exportarFormatos(Request $request, HelperService $helper, QueryService $queryService)
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');

        $validated = $request->validate([
            'id_nomina_gape_cliente' => 'required',
            'id_nomina_gape_empresa' => 'required',
            'id_esquema'             => 'required|array|min:1',
            'combinaciones'          => 'required|array|min:1',
        ]);

        $idCliente = $validated['id_nomina_gape_cliente'];
        $idEmpresa = $validated['id_nomina_gape_empresa'];

        $mapaEsquemaConfig = [
            'Sueldo IMSS'          => 'SUELDO_IMSS',
            'Asimilados'           => 'ASIMILADOS',
            'Sindicato'            => 'SINDICATO',
            'Tarjeta facil'        => 'TARJETA_FACIL',
            'Gastos por comprobar' => 'GASTOS_POR_COMPROBAR',
        ];

        $conexion = $helper->getConexionDatabaseNGE($idEmpresa, 'Nom');
        $helper->setDatabaseConnection($conexion, $conexion->nombre_base);

        $configDispersion = ConfigDispersionService::getConfig();

        $requestId = uniqid('req_', true);

        $tmpDir = "dispersion_tmp/{$requestId}";
        $zipDir = "dispersion_zip/{$requestId}";

        Storage::disk('public')->makeDirectory($tmpDir);

        // ⚠️ asegurar carpeta ZIP físicamente
        $zipDirAbsolute = storage_path("app/public/{$zipDir}");
        if (!is_dir($zipDirAbsolute)) {
            mkdir($zipDirAbsolute, 0755, true);
        }


        $zipName = "dispersion_{$requestId}.zip";
        $zipPath = storage_path("app/public/{$zipDir}/{$zipName}");

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('No se pudo crear el ZIP');
        }

        foreach ($request->combinaciones as $row) {

            // 1️⃣ Determinar esquema base
            $primerBanco = collect($row['bancos'])->first();

            if (!$primerBanco || empty($primerBanco['esquema'])) {
                continue;
            }

            $clave = $mapaEsquemaConfig[$primerBanco['esquema']] ?? null;
            if (!$clave || !isset($configDispersion[$clave])) {
                continue;
            }

            $idCombinacion = (int)$row['id_esquema'];

            $configHoja = $configDispersion[$clave];
            $consulta   = $configHoja['queries']['detalle'];

            $combo = collect($request->combinaciones)
                ->firstWhere('id_esquema', $idCombinacion);

            if (!$combo) {
                continue;
            }

            $requestCombo = new Request(array_merge(
                $request->all(),
                $combo,
                [
                    'id_nomina_gape_esquema' => $idCombinacion,
                ]
            ));

            /*
            dd([
                'row' => $combo,
            ]);
            */

            // 2️⃣ Ejecutar UNA consulta base
            $dataDetalle = collect(
                $queryService->getData($consulta, $requestCombo)
            )->map(fn($r) => (array)$r)->toArray();
            /*
            dd([
                'row' => $dataDetalle,
            ]);
            */

            if (empty($dataDetalle)) {
                continue;
            }

            $indices = array_keys($dataDetalle[0]);

            // 3️⃣ Detectar columnas dinámicas (esquemas)
            $columnasEsquemas = $this->getColumnasPorEsquema($indices);

            // 4️⃣ Iterar bancos seleccionados
            foreach ($row['bancos'] as $bancoSeleccionado) {

                $esquemaBanco = $bancoSeleccionado['esquema'];

                if (!in_array($esquemaBanco, $columnasEsquemas)) {
                    continue;
                }

                // 5️⃣ Filtrar datos por esquema
                $detalleFiltrado = $this->filtrarDetallePorEsquema(
                    $dataDetalle,
                    $esquemaBanco
                );

                /*
                dd([
                    'row' => $detalleFiltrado,
                ]);
                */

                if (empty($detalleFiltrado)) {
                    continue;
                }

                // 6️⃣ Obtener configuración bancaria
                $configBanco = NominaGapeBancoConfiguracionEsquema::from('nomina_gape_banco_configuracion_esquema as ngbce')
                    ->join('nomina_gape_banco AS ngb', 'ngbce.id_nomina_gape_banco', '=', 'ngb.id')
                    ->where('ngbce.id', $bancoSeleccionado['id_nomina_gape_banco'])
                    ->select(
                        'ngb.banco',
                        'ngb.clave_banco',
                        'ngbce.azteca_cuenta_origen',
                        'ngbce.banorte_cuenta_origen',
                        'ngbce.banorte_clave_banco'
                    )
                    ->first();

                if (!$configBanco) continue;

                $safeEsquema = str_replace(' ', '_', strtoupper($esquemaBanco));
                $baseName = "{$configBanco->banco}_{$safeEsquema}_" . now()->format('His');

                $filename = null;
                $relativeTmp = null;

                $concepto = "PAGO";

                if (in_array($safeEsquema, $this->hojasFiscal)) {
                    $concepto = "NOMINA";
                } else {
                    $concepto = "PAGO";
                }

                /*
                dd([
                    'row' => $concepto,
                ]);
                */

                switch ($configBanco->banco) {

                    case 'Fondeadora':

                        $filename = "{$baseName}.csv";
                        $relativeTmp = "{$tmpDir}/{$filename}";

                        Excel::store(
                            new FondeadoraExport(collect($detalleFiltrado), $concepto),
                            $relativeTmp,
                            'public'
                        );

                        $absoluteTmp = storage_path("app/public/{$relativeTmp}");

                        $zip->addFile($absoluteTmp, $filename);
                        break;
                    case 'Banorte':

                        // Terceros 02

                        $filenameTerceros = "{$baseName}_terceros.xlsx";
                        $relativeTmpTerceros = "{$tmpDir}/{$filenameTerceros}";

                        $ordenante = $this->datosEmpresaNomina();

                        Excel::store(
                            new BanorteTerceroExport(
                                collect($detalleFiltrado),
                                $configBanco->banorte_cuenta_origen,
                                $ordenante->rfc,
                                $concepto
                            ),
                            $relativeTmpTerceros,
                            'public'
                        );

                        $absoluteTmpTercero = storage_path("app/public/{$relativeTmpTerceros}");

                        $zip->addFile($absoluteTmpTercero, $filenameTerceros);

                        // Interbancarios 04

                        $filenameInter = "{$baseName}_interbancarios.xlsx";
                        $relativeTmpInter = "{$tmpDir}/{$filenameInter}";

                        Excel::store(
                            new BanorteInterbancarioExport(
                                collect($detalleFiltrado),
                                $configBanco->banorte_cuenta_origen,
                                $ordenante->rfc,
                                $concepto
                            ),
                            $relativeTmpInter,
                            'public'
                        );

                        $absoluteTmpInter = storage_path("app/public/{$relativeTmpInter}");

                        $zip->addFile($absoluteTmpInter, $filenameInter);

                        break;
                    case 'Azteca Bancario':
                        $filename = "AZTECA_BANCARIO_{$esquemaBanco}.xlsx";

                        Excel::store(
                            new AztecaBancarioExport(
                                collect($detalleFiltrado),
                                $configBanco->azteca_cuenta_origen,
                                'ordenanteNombreEmpresaFiscal',
                                'ordenanteRFC'
                            ),
                            "public/{$filename}",
                            null,
                            \Maatwebsite\Excel\Excel::XLSX
                        );

                        $zip->addFile(storage_path("app/public/{$filename}"), $filename);

                        /*
                        return Excel::download(
                            new AztecaBancarioExport(collect($detalleFiltrado), $configBanco->azteca_cuenta_origen, "ordenanteNombreEmpresaFiscal", "ordenanteRFC"),
                            'dispersion_bancaria.xlsx',
                            \Maatwebsite\Excel\Excel::XLSX
                        );
                        */
                        break;
                    case 'Azteca Interbancario':
                        $filename = "AZTECA_INTERBANCARIO_{$esquemaBanco}.xlsx";

                        Excel::store(
                            new AztecaInterbancarioExport(
                                collect($detalleFiltrado),
                                $configBanco->azteca_cuenta_origen,
                                'ordenanteNombreEmpresaFiscal',
                                'ordenanteRFC'
                            ),
                            "public/{$filename}",
                            null,
                            \Maatwebsite\Excel\Excel::XLSX
                        );

                        $zip->addFile(storage_path("app/public/{$filename}"), $filename);

                        /*
                        return Excel::download(
                            new AztecaInterbancarioExport(collect($detalleFiltrado), $configBanco->azteca_cuenta_origen, "ordenanteNombreEmpresaFiscal", "ordenanteRFC"),
                            'dispersion_interbancaria.xlsx',
                            \Maatwebsite\Excel\Excel::XLSX
                        );
                        */
                        break;
                    case 'Tarjeta facil':
                        /*
                        return Excel::download(
                            new AztecaInterbancarioExport(collect($detalleFiltrado), $configBanco->azteca_cuenta_origen, "ordenanteNombreEmpresaFiscal", "ordenanteRFC"),
                            'dispersion_interbancaria.xlsx',
                            \Maatwebsite\Excel\Excel::XLSX
                        );
                        break;
                        */
                }
            }
        }

        $zip->close();

        // borra SOLO la carpeta de esta petición
        // Storage::disk('public')->deleteDirectory("dispersion_tmp/{$requestId}");

        /*return response()
            ->download($zipPath);
            */
    }

    private function getIndices(array $data): array
    {
        return !empty($data) && is_array($data[0])
            ? array_keys($data[0])
            : [];
    }

    public function datosEmpresaNomina()
    {
        return Empresa::select(
            DB::raw("
                rfc +
                SUBSTRING(CONVERT(char(10), fechaconstitucion, 126), 3, 2) +
                SUBSTRING(CONVERT(char(10), fechaconstitucion, 126), 6, 2) +
                SUBSTRING(CONVERT(char(10), fechaconstitucion, 126), 9, 2) +
                homoclave
                AS rfc,
                NombreEmpresaFiscal")
        )
            ->first();
    }

    public function bancosPorCombinacion(Request $request)
    {
        try {
            $validated = $request->validate([
                'idEmpresa'     => 'required|integer',
                'idCliente'     => 'required|integer',
                'idEsquema'     => 'required',
            ]);

            $idNominaGapeEmpresa   = $validated['idEmpresa'];
            $idNominaGapeCliente   = $validated['idCliente'];
            $idCombinacion   = $validated['idEsquema'];

            $bancos = NominaGapeBancoConfiguracionEsquema::query()
                ->join(
                    'nomina_gape_banco as ngb',
                    'nomina_gape_banco_configuracion_esquema.id_nomina_gape_banco',
                    '=',
                    'ngb.id'
                )
                ->join(
                    'nomina_gape_esquema as nge',
                    'nomina_gape_banco_configuracion_esquema.id_nomina_gape_esquema',
                    '=',
                    'nge.id'
                )
                ->join(
                    'nomina_gape_cliente_esquema_combinacion as ngcec',
                    function ($join) {
                        $join->on(
                            'nomina_gape_banco_configuracion_esquema.id_nomina_gape_cliente',
                            '=',
                            'ngcec.id_nomina_gape_cliente'
                        )->on(
                            'nomina_gape_banco_configuracion_esquema.id_nomina_gape_empresa',
                            '=',
                            'ngcec.id_nomina_gape_empresa'
                        )->on(
                            'nomina_gape_banco_configuracion_esquema.id_nomina_gape_esquema',
                            '=',
                            'ngcec.id_nomina_gape_esquema'
                        );
                    }
                )
                ->where('nomina_gape_banco_configuracion_esquema.id_nomina_gape_cliente', $idNominaGapeCliente)
                ->where('nomina_gape_banco_configuracion_esquema.id_nomina_gape_empresa', $idNominaGapeEmpresa)
                ->where('ngcec.combinacion', $idCombinacion)
                ->where('nomina_gape_banco_configuracion_esquema.activo_dispersion', 1)
                ->select([
                    'nomina_gape_banco_configuracion_esquema.id as id',
                    'nge.esquema AS esquema',
                    DB::raw("
                        CONCAT_WS(
                            ' : ',
                            ngb.banco,
                            nomina_gape_banco_configuracion_esquema.azteca_cuenta_origen,
                            nomina_gape_banco_configuracion_esquema.banorte_cuenta_origen,
                            nomina_gape_banco_configuracion_esquema.banorte_clave_banco
                        ) as descripcion
                    "),
                ])
                ->orderBy('orden')
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $bancos,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los esquemas por tipo de periodo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


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
