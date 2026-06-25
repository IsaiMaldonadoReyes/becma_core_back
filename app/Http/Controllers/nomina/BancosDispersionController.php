<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;

use ZipArchive;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use App\Models\nomina\GAPE\NominaGapeBancoConfiguracionEsquema;
use App\Models\nomina\GAPE\NominaGapeDispersionHistorial;
use App\Models\nomina\GAPE\NominaGapeConfiguracionGlobal;

use App\Models\nomina\default\Empresa;
// refactor

use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;

use App\Exports\FondeadoraExport;
use App\Exports\AztecaTerceroExport;
use App\Exports\AztecaInterbancarioExport;
use App\Exports\BanorteTerceroExport;
use App\Exports\BanorteInterbancarioExport;
use App\Exports\BanorteTerceroTxtExport;
use App\Exports\BanorteInterbancarioTxtExport;
use App\Exports\TarjetaFacilExport;
use App\Exports\BankaoolExcelExport;

use App\Exports\SantanderInterbancariosTxtExport;
use App\Exports\SantanderTercerosTxtExport;

use App\Exports\AfirmeTxtExport;
use App\Exports\AztecaExcelExport;

use App\Exports\BanorteWebPagExport;

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
            'ap',
            'am',
            'nombreCompleto',
            'rfc',
            'claveBanco',
            'cuentaPagoElectronico',
            'clabeInterbancaria',
            'campoextra1',
            'campoextra3',
            'tarjetafacil',
            'bancoDestinoBankaool',
            'bancoClaveTransferencia',
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
                'ap'                        => $row['ap'],
                'am'                        => $row['am'],
                'nombreCompleto'            => $row['nombreCompleto'],
                'rfc'                       => $row['rfc'],
                'claveBanco'                => $row['claveBanco'],
                'cuentaPagoElectronico'     => $row['cuentaPagoElectronico'],
                'clabeInterbancaria'        => $row['clabeInterbancaria'],
                'campoextra1'               => $row['campoextra1'],
                'campoextra3'               => $row['campoextra3'],
                'tarjetafacil'              => $row['tarjetafacil'],
                'importe'                   => $row[$esquema],
                'bancoDestinoBankaool'      => $row['bancoDestinoBankaool'],
                'bancoClaveTransferencia'      => $row['bancoClaveTransferencia'],
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
                    ->leftJoin('nomina_gape_banco_configuracion_datos_extra as ngbcde', 'ngbce.id', '=', 'ngbcde.id_nomina_gape_banco_configuracion')
                    ->where('ngbce.id', $bancoSeleccionado['id_nomina_gape_banco'])
                    ->when(!empty($bancoSeleccionado['id_datos_extra']), function ($query) use ($bancoSeleccionado) {
                        $query->where('ngbcde.id', $bancoSeleccionado['id_datos_extra']);
                    })
                    ->select(
                        'ngbce.id',
                        'ngb.banco',
                        'ngb.clave_banco',
                        'ngb.clave_interna',
                        'ngbcde.cuenta',
                    )
                    ->first();

                if (!$configBanco) continue;

                $camposAdicionales = $bancoSeleccionado['campos_adicionales'] ?? [];

                $cuentaOrigen = $configBanco->cuenta ?? null;

                $safeEsquema = str_replace(' ', '_', strtoupper($esquemaBanco));
                $baseName = "{$configBanco->banco}_{$safeEsquema}_" . now()->format('His');

                $filename = null;
                $relativeTmp = null;
                /*
                dd([
                    'row' => $concepto,
                ]);
                */

                switch ($configBanco->clave_interna) {
                    case 'fondeadora_excel':

                        $filename = "{$baseName}.csv";
                        $relativeTmp = "{$tmpDir}/{$filename}";

                        $descripcion = $camposAdicionales['descripcion_layout'] ?? null;

                        Excel::store(
                            new FondeadoraExport(collect($detalleFiltrado), $descripcion),
                            $relativeTmp,
                            'public'
                        );

                        $absoluteTmp = storage_path("app/public/{$relativeTmp}");

                        $zip->addFile($absoluteTmp, $filename);
                        break;
                    case 'Banorte':

                        // Terceros 02

                        $filenameTerceros = "{$baseName}_terceros.csv";
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

                        $filenameInter = "{$baseName}_interbancarios.csv";
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
                    case 'banorte_terceros_txt':

                        // Terceros 02

                        $folioManual = $camposAdicionales['folio_layout'] ?? null;

                        $folioConsecutivo = $this->obtenerOGenerarFolioDispersion([
                            'id_nomina_gape_cliente' => $idCliente,
                            'id_nomina_gape_empresa' => $idEmpresa,
                            'id_nomina_gape_banco_configuracion' => $configBanco->id,
                            'ejercicio' => $row['id_ejercicio'] ?? null,
                            'periodo' => $row['periodo_inicial'] ?? null,
                            'folio_manual' => $folioManual,
                            'monto_nomina' => collect($detalleFiltrado)->sum('importe'),
                            'total_empleados' => collect($detalleFiltrado)->count(),
                        ]);

                        $descripcion = $camposAdicionales['descripcion_layout'] ?? null;

                        $filenameTerceros = "{$baseName}_terceros.txt";
                        $relativeTmpTerceros = "{$tmpDir}/{$filenameTerceros}";

                        $ordenante = $this->datosEmpresaNomina();

                        Excel::store(
                            new BanorteTerceroTxtExport(
                                collect($detalleFiltrado),
                                $cuentaOrigen,
                                $ordenante->rfc,
                                $folioConsecutivo,
                                $descripcion
                            ),
                            $relativeTmpTerceros,
                            'public',
                            ExcelWriter::CSV
                        );

                        $absoluteTmpTercero = storage_path("app/public/{$relativeTmpTerceros}");

                        $zip->addFile($absoluteTmpTercero, $filenameTerceros);
                        break;
                    case 'banorte_interbancarios_txt':
                        // Interbancarios 04

                        $folioManual = $camposAdicionales['folio_layout'] ?? null;

                        $folioConsecutivo = $this->obtenerOGenerarFolioDispersion([
                            'id_nomina_gape_cliente' => $idCliente,
                            'id_nomina_gape_empresa' => $idEmpresa,
                            'id_nomina_gape_banco_configuracion' => $configBanco->id,
                            'ejercicio' => $row['id_ejercicio'] ?? null,
                            'periodo' => $row['periodo_inicial'] ?? null,
                            'folio_manual' => $folioManual,
                            'monto_nomina' => collect($detalleFiltrado)->sum('importe'),
                            'total_empleados' => collect($detalleFiltrado)->count(),
                        ]);

                        $descripcion = $camposAdicionales['descripcion_layout'] ?? null;

                        $filenameInter = "{$baseName}_interbancarios.txt";
                        $relativeTmpInter = "{$tmpDir}/{$filenameInter}";

                        $ordenante = $this->datosEmpresaNomina();

                        Excel::store(
                            new BanorteInterbancarioTxtExport(
                                collect($detalleFiltrado),
                                $cuentaOrigen,
                                $ordenante->rfc,
                                $folioConsecutivo,
                                $descripcion
                            ),
                            $relativeTmpInter,
                            'public',
                            ExcelWriter::CSV
                        );

                        $absoluteTmpInter = storage_path("app/public/{$relativeTmpInter}");

                        $zip->addFile($absoluteTmpInter, $filenameInter);

                        break;
                    case 'bankaool_excel':

                        $folioManual = $camposAdicionales['folio_layout'] ?? null;

                        $folioConsecutivo = $this->obtenerOGenerarFolioDispersion([
                            'id_nomina_gape_cliente' => $idCliente,
                            'id_nomina_gape_empresa' => $idEmpresa,
                            'id_nomina_gape_banco_configuracion' => $configBanco->id,
                            'ejercicio' => $row['id_ejercicio'] ?? null,
                            'periodo' => $row['periodo_inicial'] ?? null,
                            'folio_manual' => $folioManual,
                            'monto_nomina' => collect($detalleFiltrado)->sum('importe'),
                            'total_empleados' => collect($detalleFiltrado)->count(),
                        ]);


                        $descripcion = $camposAdicionales['descripcion_layout'] ?? null;


                        $filename = "{$baseName}.xlsx";
                        $relativeTmp = "{$tmpDir}/{$filename}";

                        $export = new BankaoolExcelExport(
                            collect($detalleFiltrado),
                            $folioConsecutivo,
                            $descripcion
                        );

                        $absoluteTmp = $export->store($relativeTmp, 'public');

                        $zip->addFile($absoluteTmp, $filename);

                        break;
                    case 'Azteca':
                        // Terceros 02
                        $filenameTerceros = "{$baseName}_terceros.xlsx";
                        $relativeTmpTerceros = "{$tmpDir}/{$filenameTerceros}";
                        $ordenante = $this->datosEmpresaNomina();

                        Excel::store(
                            new AztecaTerceroExport(
                                collect($detalleFiltrado),
                                $configBanco->azteca_cuenta_origen,
                                $ordenante->NombreEmpresaFiscal,
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
                            new AztecaInterbancarioExport(
                                collect($detalleFiltrado),
                                $configBanco->azteca_cuenta_origen,
                                $ordenante->NombreEmpresaFiscal,
                                $ordenante->rfc,
                                $concepto
                            ),
                            $relativeTmpInter,
                            'public'
                        );

                        $absoluteTmpInter = storage_path("app/public/{$relativeTmpInter}");

                        $zip->addFile($absoluteTmpInter, $filenameInter);

                        break;
                    case 'tarjeta_facil_excel':
                        $filename = "{$baseName}.xlsx";
                        $relativeTmp = "{$tmpDir}/{$filename}";
                        $ordenante = $this->datosEmpresaNomina();
                        Excel::store(
                            new TarjetaFacilExport(collect($detalleFiltrado), $ordenante->NombreEmpresaFiscal),
                            $relativeTmp,
                            'public'
                        );

                        $absoluteTmp = storage_path("app/public/{$relativeTmp}");

                        $zip->addFile($absoluteTmp, $filename);
                        break;

                    case 'santander_interbancarios_txt':
                        // Interbancarios LTX05

                        $folioManual = $camposAdicionales['folio_layout'] ?? null;

                        $folioConsecutivo = $this->obtenerOGenerarFolioDispersion([
                            'id_nomina_gape_cliente' => $idCliente,
                            'id_nomina_gape_empresa' => $idEmpresa,
                            'id_nomina_gape_banco_configuracion' => $configBanco->id,
                            'ejercicio' => $row['id_ejercicio'] ?? null,
                            'periodo' => $row['periodo_inicial'] ?? null,
                            'folio_manual' => $folioManual,
                            'monto_nomina' => collect($detalleFiltrado)->sum('importe'),
                            'total_empleados' => collect($detalleFiltrado)->count(),
                        ]);

                        $descripcion = $camposAdicionales['descripcion_layout'] ?? '';

                        $filenameInter = "{$baseName}_interbancarios.txt";
                        $relativeTmpInter = "{$tmpDir}/{$filenameInter}";

                        /*
                        dd([
                            'row' => $detalleFiltrado,
                        ]);*/

                        $export = new SantanderInterbancariosTxtExport(
                            collect($detalleFiltrado),
                            $cuentaOrigen,
                            $folioConsecutivo,
                            $descripcion
                        );

                        $absoluteTmpInter = $export->storeTxt($relativeTmpInter);

                        $zip->addFile($absoluteTmpInter, $filenameInter);

                        break;
                    case 'santander_terceros_txt':

                        $folioManual = $camposAdicionales['folio_layout'] ?? null;

                        $folioConsecutivo = $this->obtenerOGenerarFolioDispersion([
                            'id_nomina_gape_cliente' => $idCliente,
                            'id_nomina_gape_empresa' => $idEmpresa,
                            'id_nomina_gape_banco_configuracion' => $configBanco->id,
                            'ejercicio' => $row['id_ejercicio'] ?? null,
                            'periodo' => $row['periodo_inicial'] ?? null,
                            'folio_manual' => $folioManual,
                            'monto_nomina' => collect($detalleFiltrado)->sum('importe'),
                            'total_empleados' => collect($detalleFiltrado)->count(),
                        ]);

                        $fechaAplicacion = $camposAdicionales['fecha_aplicacion'] ?? null;

                        $filenameInter = "{$baseName}_terceros.txt";
                        $relativeTmpInter = "{$tmpDir}/{$filenameInter}";

                        /*
                        dd([
                            'row' => $detalleFiltrado,
                        ]);*/

                        $export = new SantanderTercerosTxtExport(
                            collect($detalleFiltrado),
                            $cuentaOrigen,
                            $fechaAplicacion
                        );

                        $absoluteTmpInter = $export->storeTxt($relativeTmpInter);

                        $zip->addFile($absoluteTmpInter, $filenameInter);

                        break;

                    case 'afirme_txt':

                        $folioManual = $camposAdicionales['folio_layout'] ?? null;

                        $folioConsecutivo = $this->obtenerOGenerarFolioDispersion([
                            'id_nomina_gape_cliente' => $idCliente,
                            'id_nomina_gape_empresa' => $idEmpresa,
                            'id_nomina_gape_banco_configuracion' => $configBanco->id,
                            'ejercicio' => $row['id_ejercicio'] ?? null,
                            'periodo' => $row['periodo_inicial'] ?? null,
                            'folio_manual' => $folioManual,
                            'monto_nomina' => collect($detalleFiltrado)->sum('importe'),
                            'total_empleados' => collect($detalleFiltrado)->count(),
                        ]);

                        $descripcion = $camposAdicionales['descripcion_layout'] ?? '';
                        $fechaAplicacion = $camposAdicionales['fecha_aplicacion'] ?? now()->format('Y-m-d');

                        $filename = "{$baseName}_afirme.txt";
                        $relativeTmp = "{$tmpDir}/{$filename}";

                        $ordenante = $this->datosEmpresaNomina();

                        $export = new AfirmeTxtExport(
                            collect($detalleFiltrado),
                            $cuentaOrigen,
                            $ordenante->rfc,
                            $ordenante->NombreEmpresaFiscal,
                            $fechaAplicacion,
                            $descripcion
                        );

                        $absoluteTmp = $export->storeTxt($relativeTmp);

                        $zip->addFile($absoluteTmp, $filename);

                        break;

                    case 'azteca_excel':

                        $filename = "{$baseName}.xlsx";
                        $relativeTmp = "{$tmpDir}/{$filename}";

                        $descripcion = $camposAdicionales['descripcion_layout'] ?? null;

                        Excel::store(
                            new AztecaExcelExport(collect($detalleFiltrado), $descripcion),
                            $relativeTmp,
                            'public'
                        );

                        $absoluteTmp = storage_path("app/public/{$relativeTmp}");

                        $zip->addFile($absoluteTmp, $filename);
                        break;

                    case 'banorte_web_pag':

                        $fechaAplicacion = $camposAdicionales['fecha_aplicacion'] ?? now()->format('Y-m-d');

                        $filename = "{$baseName}.pag";
                        $relativeTmp = "{$tmpDir}/{$filename}";

                        $export = new BanorteWebPagExport(
                            collect($detalleFiltrado),
                            $cuentaOrigen,
                            $fechaAplicacion
                        );

                        $absoluteTmp = $export->storeTxt($relativeTmp);

                        $zip->addFile($absoluteTmp, $filename);

                        break;
                }
            }
        }

        $zip->close();

        register_shutdown_function(function () use ($tmpDir) {
            Storage::disk('public')->deleteDirectory($tmpDir);
        });

        return response()
            ->download($zipPath)
            ->deleteFileAfterSend(true);
    }

    private function obtenerOGenerarFolioDispersion(array $data): string
    {

        if (!empty($data['folio_manual'])) {
            return (string) $data['folio_manual'];
        }

        return DB::transaction(function () use ($data) {

            $historialExistente = NominaGapeDispersionHistorial::where([
                'id_nomina_gape_cliente' => $data['id_nomina_gape_cliente'],
                'id_nomina_gape_empresa' => $data['id_nomina_gape_empresa'],
                'id_nomina_gape_banco_configuracion' => $data['id_nomina_gape_banco_configuracion'],
                'ejercicio' => $data['ejercicio'],
                'periodo' => $data['periodo'],
            ])
                ->first();

            if ($historialExistente) {
                return (string) $historialExistente->folio_dispersion;
            }

            if (!empty($data['folio_manual'])) {
                $folio = (string) $data['folio_manual'];
            } else {
                $ultimoFolioHistorial = NominaGapeDispersionHistorial::whereNotNull('folio_dispersion')
                    ->lockForUpdate()
                    ->orderByRaw('TRY_CAST(folio_dispersion AS BIGINT) DESC')
                    ->value('folio_dispersion');

                if ($ultimoFolioHistorial !== null) {
                    $folio = (string) ((int) $ultimoFolioHistorial + 1);
                } else {
                    $configGlobal = NominaGapeConfiguracionGlobal::lockForUpdate()->first();

                    $folioBase = $configGlobal?->folio_dispersion_actual ?? 0;

                    $folio = (string) ((int) $folioBase + 1);
                }

                NominaGapeConfiguracionGlobal::query()->update([
                    'folio_dispersion_actual' => (int) $folio,
                ]);
            }

            NominaGapeDispersionHistorial::create([
                'id_nomina_gape_cliente' => $data['id_nomina_gape_cliente'],
                'id_nomina_gape_empresa' => $data['id_nomina_gape_empresa'],
                'id_nomina_gape_banco_configuracion' => $data['id_nomina_gape_banco_configuracion'],
                'ejercicio' => $data['ejercicio'],
                'periodo' => $data['periodo'],
                'folio_dispersion' => $folio,
                'monto_nomina' => $data['monto_nomina'] ?? null,
                'total_empleados' => $data['total_empleados'] ?? null,
            ]);

            return $folio;
        });
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
                ->leftJoin(
                    'nomina_gape_banco_configuracion_datos_extra as ngbcde',
                    'nomina_gape_banco_configuracion_esquema.id',
                    '=',
                    'ngbcde.id_nomina_gape_banco_configuracion'
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
                    'ngbcde.id as id_datos_extra',
                    'ngb.banco as banco',
                    'ngb.clave_banco as clave_banco',
                    'ngb.clave_interna as clave_interna',
                    'nge.esquema AS esquema',
                    DB::raw("
                        CASE
                            WHEN ngbcde.cuenta IS NOT NULL
                            THEN CONCAT(ngb.banco, ' : ', ngbcde.cuenta)
                            ELSE ngb.banco
                        END as descripcion
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
}
