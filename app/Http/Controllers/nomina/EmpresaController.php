<?php

namespace App\Http\Controllers\nomina;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;
use App\Http\Controllers\core\HelperController;

use App\Http\Requests\nomina\gape\empresa\StoreEmpresaRequest;
use App\Http\Requests\nomina\gape\empresa\UpdateEmpresaRequest;

use App\Models\core\Conexion;
use App\Models\nomina\GAPE\NominaGapeEmpresa;
use App\Models\nomina\GAPE\NominaGapeClienteEsquemaCombinacion;
use App\Models\nomina\GAPE\NominaGapeBancoConfiguracionEsquema;
use App\Models\nomina\GAPE\NominaGapeFormulasContpaq;
use App\Models\nomina\nomGenerales\NominaEmpresa;
use App\Models\core\EmpresaDatabase;

use App\Http\Services\Core\HelperService;
use App\Models\nomina\default\TipoPeriodo;
use App\Models\nomina\default\Conceptos;
use App\Models\nomina\GAPE\NominaGapeCombinacionPrevision;
use App\Models\nomina\GAPE\NominaGapeEmpresaPeriodoCombinacionParametrizacion;
use App\Models\nomina\GAPE\NominaGapeTipoPeriodo;

class EmpresaController extends Controller
{
    protected $helperController;

    public function __construct(helperController $helperController)
    {
        $this->helperController = $helperController;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $empresas = DB::table('nomina_gape_empresa as nge')
                ->leftJoin('nomina_gape_cliente as ngc', 'nge.id_nomina_gape_cliente', '=', 'ngc.id')
                ->leftJoin('empresa_database as ed', 'nge.id_empresa_database', '=', 'ed.id')
                ->select(
                    'nge.id as id',
                    'ngc.nombre as cliente',
                    DB::raw("ISNULL(ed.nombre_empresa, 'Sin empresa CONTPAQi') as empresa"),
                    DB::raw("CASE WHEN nge.fiscal = 0 THEN 'No fiscal' ELSE 'Fiscal' END as tipo"),
                    'nge.razon_social',
                    'nge.rfc',
                    'nge.codigo_interno',
                    DB::raw("FORMAT(nge.created_at, 'dd-MM-yyyy') as fecha_creacion")
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
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store2(StoreEmpresaRequest $request)
    {
        try {



            $validatedData = $request->validated();
            $empresa = NominaGapeEmpresa::create($validatedData);

            $idEmpresa = $empresa->id;


            // en combinacion se tiene que guardar el idEmpresa en id_nomina_gape_empresa, y aquí mi array de combinaciones Y MI ARRAY DE TOPES POR CADA UNA DE LAS FILAS esquemas[combinaciones] para llenar los campos de topes, orden etc
            // en el campo de combinacion debes de poner el que traes del array, tal cual la combinacion

            $combinacion = NominaGapeClienteEsquemaCombinacion::create();

            $idCombinacion = $combinacion->combinacion;


            // guardar en el campo id_nomina_gape_cliente_esquema_combinacion el campo de mi $idCombinacion

            $parametrizacion = NominaGapeEmpresaPeriodoCombinacionParametrizacion::create();

            $idParametrizacion = $parametrizacion->id;

            // en la tabla NominaGapeCombinacionPrevision tengo que guardar el id de la parametrizacion y tengo que guardar por fila el array de parametrizacion[].prevision[]

            $prevision = NominaGapeCombinacionPrevision::create();

            // en esta tabla tengo que guardar mi array de bancos

            // estado = activo_dispersion
            // y por cada uno de los array de los bancos pore ejemplo AZTECA que puede tener un array, tiene que ir en una fila independiente

            $prevision = NominaGapeBancoConfiguracionEsquema::create();



            return response()->json([
                'code' => 200,
                'message' => 'Registro guardado correctamente',
                'id' => $empresa->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Se generó un error al guardar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request, HelperService $helper)
    {
        //return true;
        DB::beginTransaction();

        try {

            /* =========================================================
         * 1️⃣ EMPRESA
         * =======================================================*/

            $empresaData = $request->input('empresa');

            $empresa = NominaGapeEmpresa::create($empresaData);
            $idEmpresa = $empresa->id;

            $formulaFalta = $empresaData['formula_con_falta'] ?? false;

            $conexion = $helper->getConexionDatabaseNGE($idEmpresa, 'Nom');

            $helper->setDatabaseConnection($conexion, $conexion->nombre_base);

            if (!empty($conexion)) {
                // 1️⃣ Conceptos existentes tipo obligación
                $conceptosExistentes = Conceptos::where('tipoconcepto', 'O')
                    ->pluck('numeroconcepto')
                    ->toArray();

                // 2️⃣ Fórmulas configuradas
                $formulasContpaq = NominaGapeFormulasContpaq::all()->sortBy('numeroconcepto');

                // 3️⃣ Insertar conceptos que no existan (sin reemplazos aún)
                $conceptosParaInsertar = $formulasContpaq
                    ->whereNotIn('numeroconcepto', $conceptosExistentes)
                    ->map(function ($item) use ($formulaFalta) {

                        return [
                            'numeroconcepto' => $item->numeroconcepto,
                            'tipoconcepto'   => 'O',
                            'descripcion'    => $item->titulo,
                            'especie'        => 0,
                            'automaticoglobal' => 1,
                            'automaticoliquidacion' => 0,
                            'imprimir'       => 1,
                            'articulo86'     => 0,
                            'leyendaimporte1' => $item->descripcion,
                            'leyendaimporte2'     => '',
                            'leyendaimporte3'     => '',
                            'leyendaimporte4'     => '',
                            'cuentacw'            => '',
                            'tipomovtocw'         => 'F',
                            'contracuentacw'      => '',
                            'contabcuentacw'      => 'G',
                            'contabcontracuentacw' => 'G',
                            'leyendavalor'        => '',
                            'formulaimportetotal' => $formulaFalta
                                ? $item->formula_sin_faltas
                                : $item->formulaimportetotal,
                            'formulaimporte1' => '0',
                            'formulaimporte2' => '0',
                            'formulaimporte3' => '0',
                            'formulaimporte4' => '0',
                            'timestamp'      => now(),
                            'FormulaValor'   => '0',
                            'CuentaGravado'       => '',
                            'CuentaExentoDeduc'   => '',
                            'CuentaExentoNoDeduc' => '',
                            'ClaveAgrupadoraSAT'  => '',
                            'MetodoDePago'        => '',
                            'TipoClaveSAT'        => '',
                            'TipoHoras'           => '',
                        ];
                    })
                    ->values()
                    ->toArray();

                if (!empty($conceptosParaInsertar)) {
                    Conceptos::insert($conceptosParaInsertar);
                }

                // 4️⃣ Volver a cargar TODOS los conceptos ya con IDs reales
                $conceptos = Conceptos::get()->keyBy('numeroconcepto');

                // 5️⃣ Definir reglas de reemplazo centralizadas
                $reemplazos = [
                    '8000' => [
                        '165' => '6001',
                        '160' => '4000',
                    ],
                    '8001' => [
                        '162' => '5000',
                        '164' => '6000',
                        '166' => '7000',
                        '169' => '7003',
                    ],
                    '8002' => [
                        '163' => '5001',
                        '167' => '7001',
                        '168' => '7002',
                        '170' => '7004',
                        '161' => '4001',
                        '158' => '4003',
                        '171' => '7005',
                    ],
                ];

                // 6️⃣ Actualizar fórmulas con IDs reales
                foreach ($reemplazos as $conceptoPadre => $dependencias) {

                    if (!isset($conceptos[$conceptoPadre])) {
                        continue;
                    }

                    $concepto = $conceptos[$conceptoPadre];
                    $formula = $concepto->formulaimportetotal;

                    foreach ($dependencias as $placeholder => $numeroConcepto) {

                        if (isset($conceptos[$numeroConcepto])) {
                            $formula = str_replace(
                                $placeholder,
                                $conceptos[$numeroConcepto]->idconcepto,
                                $formula
                            );
                        }
                    }

                    $concepto->update([
                        'formulaimportetotal' => $formula
                    ]);
                }

                DB::connection('sqlsrv_dynamic')->statement("
                    INSERT INTO nom10005 (
                        idempleado,
                        idconcepto
                    )
                    SELECT
                        e.idempleado,
                        c.idconcepto
                    FROM nom10001 e
                    CROSS JOIN nom10004 c
                    WHERE c.numeroconcepto >= 4000 AND c.numeroconcepto <= 8002
                    AND c.tipoconcepto = 'O'
                ");
            }

            /* =========================================================
         * 2️⃣ COMBINACIONES + TOPES
         * =======================================================*/

            // Mapa para relacionar combinacion_key → ids creados
            $mapCombinaciones = [];

            foreach ($request->input('combinaciones', []) as $combo) {

                foreach ($combo['esquemas'] as $esquema) {

                    $tope = collect($combo['topes'] ?? [])
                        ->firstWhere('id_esquema', $esquema['id_esquema']);

                    $row = NominaGapeClienteEsquemaCombinacion::create([
                        'estado' => $combo['estado'],
                        'id_nomina_gape_cliente' => $empresa->id_nomina_gape_cliente,
                        'id_nomina_gape_empresa' => $idEmpresa,
                        'id_nomina_gape_esquema' => $esquema['id_esquema'],
                        'combinacion' => $combo['combinacion'],
                        'tope' => $tope['tope'] ?? null,
                        'orden' => $tope['orden'] ?? null,
                    ]);

                    $mapCombinaciones[$combo['combinacion']][] = $row->id;
                }
            }

            /* =========================================================
         * 3️⃣ PARAMETRIZACIÓN
         * =======================================================*/

            // Mapa id_parametrizacion → previsiones
            $mapParametrizaciones = [];

            foreach ($request->input('parametrizacion', []) as $param) {

                $parametrizacion = NominaGapeEmpresaPeriodoCombinacionParametrizacion::create([
                    'estado' => $param['estado'],
                    'id_nomina_gape_cliente' => $empresa->id_nomina_gape_cliente,
                    'id_nomina_gape_empresa' => $idEmpresa,
                    'idtipoperiodo' => $param['id_periodo'],
                    'id_nomina_gape_cliente_esquema_combinacion' => $param['combinacion_key'],
                    'fee' => $param['fee'],
                    'base_fee' => $param['base_fee'],
                    'provisiones' => $param['provisiones'],
                ]);

                // 2) Insertar previsiones ligadas a esta parametrización
                foreach (($param['previsiones'] ?? []) as $prev) {
                    NominaGapeCombinacionPrevision::create([
                        'nomina_gape_empresa_periodo_combinacion_parametrizacion' => $parametrizacion->id,
                        'id_concepto' => $prev['idconcepto'],
                    ]);
                }
            }

            /* =========================================================
         * 5️⃣ BANCOS POR ESQUEMA
         * =======================================================*/

            foreach ($request->input('bancos', []) as $item) {

                $idEsquema = $item['id_esquema'];

                foreach ($item['bancos'] as $banco) {

                    NominaGapeBancoConfiguracionEsquema::create([
                        'estado' => $banco['estado'],
                        'activo_dispersion' => $banco['estado'],
                        'id_nomina_gape_cliente' => $empresa->id_nomina_gape_cliente,
                        'id_nomina_gape_empresa' => $idEmpresa,
                        'id_nomina_gape_esquema' => $idEsquema,

                        // ⚠️ Ajusta este mapeo según tu catálogo real
                        'id_nomina_gape_banco' => $this->mapBancoId($banco['banco']),

                        'azteca_cuenta_origen' =>
                        $banco['banco'] === 'Azteca'
                            ? ($banco['cuentas_origen'][0]['cuenta'] ?? null)
                            : null,

                        'banorte_cuenta_origen' =>
                        $banco['banco'] === 'Banorte'
                            ? ($banco['cuentas_origen'][0]['cuenta'] ?? null)
                            : null,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'code' => 200,
                'message' => 'Registro guardado correctamente',
                'id' => $empresa->id,
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'code' => 500,
                'message' => 'Se generó un error al guardar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function mapBancoId(string $nombre): int
    {
        return match ($nombre) {
            'Fondeadora' => 1,
            'Banorte' => 2,
            'Azteca' => 4,
            'Tarjeta facil' => 5,
            default => throw new \Exception("Banco no soportado: {$nombre}")
        };
    }

    /**
     * Display the specified resource.
     */
    public function show($idEmpresa, HelperService $helper)
    {
        try {

            $empresa = NominaGapeEmpresa::findOrFail($idEmpresa);

            /* =====================================================
     * 1️⃣ COMBINACIONES + TOPES
     * ===================================================== */

            $idEmpresaDatabase = $empresa->id_empresa_database;

            $tipoPeriodo = null;

            if ($idEmpresaDatabase !== null) {
                $conexion = $helper->getConexionDatabaseNGE($idEmpresa, 'Nom');
                $helper->setDatabaseConnection($conexion, $conexion->nombre_base);

                $tipoPeriodo = TipoPeriodo::select('idtipoperiodo', 'nombretipoperiodo')
                    ->get();
            } else {
                $tipoPeriodo = NominaGapeTipoPeriodo::select('id', 'nombretipoperiodo')
                    ->get();
            }

            $mapPeriodos = $tipoPeriodo->pluck(
                'nombretipoperiodo',
                $idEmpresaDatabase ? 'idtipoperiodo' : 'id'
            );

            $rows = NominaGapeClienteEsquemaCombinacion::where(
                'id_nomina_gape_empresa',
                $idEmpresa
            )->get();

            $combinaciones = $rows
                ->groupBy('combinacion')
                ->map(function ($items, $key) {

                    return [
                        'combinacion' => $key,
                        'estado' => $items->first()->estado,
                        'esquemas' => $items->map(fn($i) => [
                            'id' => $i->id_nomina_gape_esquema,
                            'esquema' => $this->getNombreEsquema($i->id_nomina_gape_esquema),
                            'contpaqi' => $this->esContpaqi($i->id_nomina_gape_esquema),
                        ])->values(),

                        'topes' => $items->map(fn($i) => [
                            'id' => $i->id,
                            'id_esquema' => $i->id_nomina_gape_esquema,
                            'tope' => $i->tope,
                            'orden' => (int) $i->orden,
                        ])->values(),
                    ];
                })
                ->values();

            /* =====================================================
     * 2️⃣ PARAMETRIZACIÓN + PREVISIONES
     * ===================================================== */
            $paramRows =
                NominaGapeEmpresaPeriodoCombinacionParametrizacion::where(
                    'id_nomina_gape_empresa',
                    $idEmpresa
                )->get();

            $previsiones =
                NominaGapeCombinacionPrevision::whereIn(
                    'nomina_gape_empresa_periodo_combinacion_parametrizacion',
                    $paramRows->pluck('id')
                )->get()
                ->groupBy('nomina_gape_empresa_periodo_combinacion_parametrizacion');

            $parametrizacion = $paramRows->map(function ($p) use ($previsiones, $mapPeriodos) {
                $idPeriodo = $p->idtipoperiodo ?? $p->id_nomina_gape_tipo_periodo;

                /*
                dd([
                    'row' => $mapPeriodos[$idPeriodo],
                ]);
                */

                return [
                    'estado' => $p->estado,
                    'combinacionKey' => $p->id_nomina_gape_cliente_esquema_combinacion,
                    'idPeriodo' => $p->idtipoperiodo == null ? $p->id_nomina_gape_tipo_periodo : $p->idtipoperiodo,
                    'periodo' => $mapPeriodos[$idPeriodo] ?? '',
                    'fee' => $p->fee,
                    'baseFee' => $p->base_fee,
                    'provisiones' => $p->provisiones,
                    'prevision' => ($previsiones[$p->id] ?? collect())->map(fn($x) => [
                        'idconcepto' => (int) $x->id_concepto,
                    ])->values(),
                ];
            });

            /* =====================================================
     * 3️⃣ BANCOS POR ESQUEMA
     * ===================================================== */

            $bancosRows =
                NominaGapeBancoConfiguracionEsquema::where(
                    'id_nomina_gape_empresa',
                    $idEmpresa
                )->get()
                ->groupBy('id_nomina_gape_esquema');

            $bancos = $bancosRows->map(function ($rows, $idEsquema) {
                return [
                    'id_esquema' => $idEsquema,
                    'bancos' => $rows->map(function ($b) {
                        return [
                            'banco' => $this->mapBancoNombre($b->id_nomina_gape_banco),
                            'estado' => (int) $b->estado,
                            'cuentasOrigen' => collect([
                                $b->azteca_cuenta_origen,
                                $b->banorte_cuenta_origen,
                            ])->filter()->map(fn($c) => ['cuentaOrigen' => $c])->values(),
                        ];
                    })->values(),
                ];
            })->values();

            $data = [
                'empresa' => $empresa,
                'combinaciones' => $combinaciones,
                'parametrizacion' => $parametrizacion,
                'bancos' => $bancos,
            ];

            return response()->json([
                'code' => 200,
                'message' => 'Datos obtenidos correctamente',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function mapBancoNombre(?int $idBanco): string
    {
        return match ($idBanco) {
            1 => 'Fondeadora',
            2 => 'Banorte',
            4 => 'Azteca',
            5 => 'Tarjeta facil',
            default => 'Desconocido',
        };
    }

    private function getNombreEsquema(int $idEsquema): string
    {
        return match ($idEsquema) {
            1 => 'Sueldo IMSS',
            2 => 'Asimilados',
            3 => 'Sindicato',
            4 => 'Gastos por comprobar',
            5 => 'Tarjeta fácil',
            default => 'Desconocido',
        };
    }

    private function esContpaqi(int $idEsquema): bool
    {
        return in_array($idEsquema, [1, 2]); // los que tú definas
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
    public function update(Request $request, $idEmpresa)
    {
        DB::beginTransaction();

        try {

            /* =========================================================
         * 1️⃣ EMPRESA
         * =======================================================*/
            $empresa = NominaGapeEmpresa::findOrFail($idEmpresa);
            $empresa->update($request->input('empresa', []));

            /* =========================================================
         * 2️⃣ COMBINACIONES + TOPES
         * =======================================================*/
            $keysCombinaciones = [];

            foreach ($request->input('combinaciones', []) as $combo) {

                foreach ($combo['topes'] as $tope) {

                    $registro = NominaGapeClienteEsquemaCombinacion::updateOrCreate(
                        [
                            // 🔑 si viene el ID, se usa
                            'id' => $tope['id'] ?? null,
                        ],
                        [
                            'estado' => (bool) $combo['estado'],
                            'id_nomina_gape_cliente' => $empresa->id_nomina_gape_cliente,
                            'id_nomina_gape_empresa' => $empresa->id,
                            'id_nomina_gape_esquema' => $tope['id_esquema'],
                            'combinacion' => (string) $combo['combinacion'],
                            'tope' => $tope['tope'] ?? null,
                            'orden' => isset($tope['orden']) ? (int) $tope['orden'] : null,
                        ]
                    );

                    $keysCombinaciones[] = $registro->id;
                }
            }

            // 🔥 eliminar combinaciones que ya no vienen
            NominaGapeClienteEsquemaCombinacion::where('id_nomina_gape_empresa', $empresa->id)
                ->whereNotIn('id', $keysCombinaciones)
                ->delete();

            /* =========================================================
         * 3️⃣ PARAMETRIZACIÓN + PREVISIONES
         * =======================================================*/
            $idsParametrizacion = [];

            foreach ($request->input('parametrizacion', []) as $param) {

                $parametrizacion = NominaGapeEmpresaPeriodoCombinacionParametrizacion::updateOrCreate(
                    [
                        'id_nomina_gape_empresa' => $empresa->id,
                        'id_nomina_gape_cliente_esquema_combinacion' => (string) $param['combinacion_key'],
                        'idtipoperiodo' => $param['id_periodo'],
                    ],
                    [
                        'estado' => (bool) $param['estado'],
                        'id_nomina_gape_cliente' => $empresa->id_nomina_gape_cliente,
                        'fee' => $param['fee'],
                        'base_fee' => $param['base_fee'],
                        'provisiones' => $param['provisiones'],
                    ]
                );

                $idsParametrizacion[] = $parametrizacion->id;

                // 🔁 reset controlado de previsiones
                NominaGapeCombinacionPrevision::where(
                    'nomina_gape_empresa_periodo_combinacion_parametrizacion',
                    $parametrizacion->id
                )->delete();

                foreach (($param['previsiones'] ?? []) as $prev) {
                    NominaGapeCombinacionPrevision::create([
                        'nomina_gape_empresa_periodo_combinacion_parametrizacion' => $parametrizacion->id,
                        'id_concepto' => $prev['idconcepto'],
                    ]);
                }
            }

            // 🔥 eliminar parametrizaciones que ya no vienen
            NominaGapeEmpresaPeriodoCombinacionParametrizacion::where('id_nomina_gape_empresa', $empresa->id)
                ->whereNotIn('id', $idsParametrizacion)
                ->delete();

            /* =========================================================
         * 4️⃣ BANCOS POR ESQUEMA
         * =======================================================*/
            $idsBancos = [];

            foreach ($request->input('bancos', []) as $item) {

                $idEsquema = $item['id_esquema'];

                foreach ($item['bancos'] as $banco) {

                    $cuentas = $banco['cuentasOrigen']
                        ?? $banco['cuentas_origen']
                        ?? [];

                    $registro = NominaGapeBancoConfiguracionEsquema::updateOrCreate(
                        [
                            'id_nomina_gape_empresa' => $empresa->id,
                            'id_nomina_gape_esquema' => $idEsquema,
                            'id_nomina_gape_banco' => $this->mapBancoId($banco['banco']),
                        ],
                        [
                            'estado' => (bool) $banco['estado'],
                            'activo_dispersion' => (bool) $banco['estado'],
                            'id_nomina_gape_cliente' => $empresa->id_nomina_gape_cliente,

                            'azteca_cuenta_origen' =>
                            $banco['banco'] === 'Azteca'
                                ? ($cuentas[0]['cuenta'] ?? null)
                                : null,

                            'banorte_cuenta_origen' =>
                            $banco['banco'] === 'Banorte'
                                ? ($cuentas[0]['cuenta'] ?? null)
                                : null,
                        ]
                    );

                    $idsBancos[] = $registro->id;
                }
            }

            // 🔥 eliminar bancos que ya no vienen
            NominaGapeBancoConfiguracionEsquema::where('id_nomina_gape_empresa', $empresa->id)
                ->whereNotIn('id', $idsBancos)
                ->delete();

            DB::commit();

            return response()->json([
                'code' => 200,
                'message' => 'Registro actualizado correctamente',
                'id' => $empresa->id,
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'code' => 500,
                'message' => 'Error al actualizar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function empresasNominasSinAsginar()
    {
        try {
            $empresas = DB::table('empresa_database as ed')
                ->leftJoin('nomina_gape_empresa as nge', 'nge.id_empresa_database', '=', 'ed.id')
                ->leftJoin('conexion as con', 'ed.id_conexion', '=', 'con.id')
                ->leftJoin('sistema as sis', 'con.id_sistema', '=', 'sis.id')
                ->select('ed.id', 'ed.nombre_empresa', 'ed.nombre_base')
                ->where('ed.estado', 1)
                ->where('sis.codigo', 'Nom')
                ->whereNull('nge.id_nomina_gape_cliente') // 👈 solo las NO relacionadas
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $empresas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener las empresas disponibles',
                'error' => $e->getMessage(),
            ]);
        }
    }

    //** Sirve para traer las empresas de nóminas por el ID del cliente y saber si es fiscal y no fiscal */
    public function empresasNominasPorClienteTipo(Request $request)
    {
        $idCliente = $request->idCliente; // ejemplo, o puedes obtenerlo del usuario autenticado
        $fiscal = $request->fiscal; // ejemplo, o puedes obtenerlo del usuario autenticado

        try {
            $empresas = DB::table('empresa_database as ed')
                ->leftJoin('nomina_gape_empresa as nge', 'nge.id_empresa_database', '=', 'ed.id')
                ->leftJoin('conexion as con', 'ed.id_conexion', '=', 'con.id')
                ->leftJoin('sistema as sis', 'con.id_sistema', '=', 'sis.id')
                ->select('ed.id', 'ed.nombre_empresa', 'ed.nombre_base')
                ->where('ed.estado', 1)
                ->where('sis.codigo', 'Nom')
                ->where('nge.id_nomina_gape_cliente', $idCliente) // 👈 solo las NO relacionadas
                ->where('nge.fiscal', $fiscal) // 👈 solo las NO relacionadas
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $empresas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener las empresas disponibles',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function asignadasACliente(Request $request)
    {
        $idCliente = $request->idCliente; // ejemplo, o puedes obtenerlo del usuario autenticado

        try {
            $empresas = EmpresaDatabase::query()
                ->leftJoin('nomina_gape_empresa as nge', 'nge.id_empresa_database', '=', 'empresa_database.id')
                ->leftJoin('conexion as con', 'empresa_database.id_conexion', '=', 'con.id')
                ->leftJoin('sistema as sis', 'con.id_sistema', '=', 'sis.id')
                ->select('empresa_database.id', 'empresa_database.nombre_empresa', 'empresa_database.nombre_base')
                ->where('empresa_database.estado', 1)
                ->where('sis.codigo', 'Nom')
                ->where('nge.id_nomina_gape_cliente', $idCliente)
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $empresas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener las empresas disponibles',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function asignadasAClienteTipo(Request $request)
    {
        // VALIDACIÓN BÁSICA
        $validated = $request->validate([
            'idCliente'     => 'required',
        ]);

        $idCliente = $validated['idCliente'];

        try {
            $empresas = NominaGapeEmpresa::query()
                ->select('id', 'id_empresa_database', 'razon_social', 'rfc')
                ->where('id_nomina_gape_cliente', $idCliente)
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $empresas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener las empresas disponibles',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function empresaDatosNominaPorCliente(Request $request, HelperService $helper)
    {
        try {
            $validated = $request->validate([
                'idCliente'     => 'required',
                'idEmpresa'     => 'required',
                'nombreBase'    => 'required',
            ]);

            $idCliente = $validated['idCliente'];
            $idEmpresa = $validated['idEmpresa'];
            $nombreEmpresa = $validated['nombreBase'];

            /**
             * 1️⃣ Obtener los datos base de la empresa desde CONTPAQi Nóminas
             */

            $conexion = $helper->getConexionDatabaseById($idEmpresa, 'Nom');
            $helper->setDatabaseConnection($conexion, $conexion->database_maestra);

            // Buscar SOLO la empresa seleccionada
            $empresaNomina = NominaEmpresa::select(
                'IDEmpresa',
                'NombreEmpresa',
                'NombreCorto',
                'RutaEmpresa',
                DB::raw("rfc + SUBSTRING(CONVERT(char(10),fechaconstitucion , 126), 3,2)
                    + SUBSTRING(CONVERT(char(10),fechaconstitucion , 126), 6,2)
                    + SUBSTRING(CONVERT(char(10),fechaconstitucion, 126), 9,2)
                    + homoclave AS rfc")
            )
                ->where('RutaEmpresa', $nombreEmpresa) // o por IDEmpresa si lo prefieres
                ->first();

            if (!$empresaNomina) {
                return response()->json([
                    'code' => 404,
                    'message' => 'La empresa no fue encontrada en CONTPAQi Nóminas.',
                ]);
            }

            /**
             * 2️⃣ Obtener (si existe) el vínculo en nomina_gape_empresa
             */
            /*
            $empresaVinculada = NominaGapeEmpresa::select(
                'razon_social',
                'rfc',
                'codigo_interno',
                'correo_notificacion'
            )
                ->where('id_nomina_gape_cliente', $idCliente)
                ->where('id_empresa_database', $idEmpresa)
                ->where('razon_social', $empresaNomina->NombreEmpresa)
                ->first();
                */

            /**
             * 3️⃣ Combinar los datos (simulando un LEFT JOIN)
             */
            $resultado = [
                'razon_social'        => $empresaNomina->NombreEmpresa,
                'rfc'                 => $empresaNomina->rfc,
                'codigo_interno'      => '',
                'correo_notificacion' => '',
            ];

            return response()->json([
                'code' => 200,
                'data' => $resultado,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener la información de la empresa',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function empresaDatosNominaPorClienteId(Request $request)
    {
        try {
            $id = $request->id;

            $empresa = NominaGapeEmpresa::select(
                'id_nomina_gape_cliente',
                'id_empresa_database',
                'fiscal',
                'razon_social',
                'rfc',
                'codigo_interno',
                'correo_notificacion',
                'fiscal',
                'estado',
                'mascara_codigo',
                'codigo_inicial',
                'codigo_actual'
            )
                ->where('id', $id)
                ->first();

            return response()->json([
                'code' => 200,
                'data' => $empresa,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener la información de la empresa',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function tipoPeriodo(Request $request, HelperService $helper)
    {
        try {
            $validated = $request->validate([
                'idEmpresaDatabase'     => 'nullable',
            ]);

            $idEmpresaDatabase = $validated['idEmpresaDatabase'];

            $tipoPeriodo = null;
            if (empty($idEmpresaDatabase)) {
                $tipoPeriodo = NominaGapeTipoPeriodo::select('id AS idtipoperiodo', 'nombretipoperiodo')
                    ->get();
            } else {
                $conexion = $helper->getConexionDatabaseById($idEmpresaDatabase, 'Nom');
                $helper->setDatabaseConnection($conexion, $conexion->nombre_base);

                $tipoPeriodo = TipoPeriodo::select('idtipoperiodo', 'nombretipoperiodo')
                    ->where('diasdelperiodo', '>', 1)
                    ->get();
            }

            return response()->json([
                'code' => 200,
                'data' => $tipoPeriodo,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener la información de la empresa',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function conceptoPrevision(Request $request, HelperService $helper)
    {
        try {
            $idEmpresaDatabase = $request->input('idEmpresaDatabase');

            $prevision = null;
            if (!empty($idEmpresaDatabase)) {

                $conexion = $helper->getConexionDatabaseById($idEmpresaDatabase, 'Nom');
                $helper->setDatabaseConnection($conexion, $conexion->nombre_base);

                $prevision = Conceptos::select('idconcepto', 'numeroconcepto', 'descripcion')
                    ->where('tipoconcepto', '=', 'P')
                    ->where('automaticoglobal', '=', '0')
                    ->get();
            }

            return response()->json([
                'code' => 200,
                'data' => $prevision,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener la información de la empresa',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function crearFormulasContpaq(Request $request, HelperService $helper)
    {
        try {

            $idEmpresaDatabase = 47;

            if (empty($idEmpresaDatabase)) {
                return response()->json(['code' => 400]);
            }

            $conexion = $helper->getConexionDatabaseNGE($idEmpresaDatabase, 'Nom');
            $helper->setDatabaseConnection($conexion, $conexion->nombre_base);

            DB::beginTransaction();

            // 1️⃣ Conceptos existentes tipo obligación
            $conceptosExistentes = Conceptos::where('tipoconcepto', 'O')
                ->pluck('numeroconcepto')
                ->toArray();

            // 2️⃣ Fórmulas configuradas
            $formulasContpaq = NominaGapeFormulasContpaq::all();

            // 3️⃣ Insertar conceptos que no existan (sin reemplazos aún)
            $conceptosParaInsertar = $formulasContpaq
                ->whereNotIn('numeroconcepto', $conceptosExistentes)
                ->map(function ($item) {

                    return [
                        'numeroconcepto' => $item->numeroconcepto,
                        'tipoconcepto'   => 'O',
                        'descripcion'    => $item->titulo,
                        'especie'        => 0,
                        'automaticoglobal' => 1,
                        'automaticoliquidacion' => 0,
                        'imprimir'       => 1,
                        'articulo86'     => 0,
                        'leyendaimporte1' => $item->descripcion,
                        'leyendaimporte2'     => '',
                        'leyendaimporte3'     => '',
                        'leyendaimporte4'     => '',
                        'cuentacw'            => '',
                        'tipomovtocw'         => 'F',
                        'contracuentacw'      => '',
                        'contabcuentacw'      => 'G',
                        'contabcontracuentacw' => 'G',
                        'leyendavalor'        => '',
                        'formulaimportetotal' => $item->formulaimportetotal, // temporal
                        'formulaimporte1' => '0',
                        'formulaimporte2' => '0',
                        'formulaimporte3' => '0',
                        'formulaimporte4' => '0',
                        'timestamp'      => now(),
                        'FormulaValor'   => '0',
                        'CuentaGravado'       => '',
                        'CuentaExentoDeduc'   => '',
                        'CuentaExentoNoDeduc' => '',
                        'ClaveAgrupadoraSAT'  => '',
                        'MetodoDePago'        => '',
                        'TipoClaveSAT'        => '',
                        'TipoHoras'           => '',
                    ];
                })
                ->values()
                ->toArray();

            if (!empty($conceptosParaInsertar)) {
                Conceptos::insert($conceptosParaInsertar);
            }

            // 4️⃣ Volver a cargar TODOS los conceptos ya con IDs reales
            $conceptos = Conceptos::get()->keyBy('numeroconcepto');

            // 5️⃣ Definir reglas de reemplazo centralizadas
            $reemplazos = [
                '8000' => [
                    '165' => '6001',
                    '160' => '4000',
                ],
                '8001' => [
                    '162' => '5000',
                    '164' => '6000',
                    '166' => '7000',
                    '169' => '7003',
                ],
                '8002' => [
                    '163' => '5001',
                    '167' => '7001',
                    '168' => '7002',
                    '170' => '7004',
                    '161' => '4001',
                    '158' => '4003',
                    '171' => '7005',
                ],
            ];

            // 6️⃣ Actualizar fórmulas con IDs reales
            foreach ($reemplazos as $conceptoPadre => $dependencias) {

                if (!isset($conceptos[$conceptoPadre])) {
                    continue;
                }

                $concepto = $conceptos[$conceptoPadre];
                $formula = $concepto->formulaimportetotal;

                foreach ($dependencias as $placeholder => $numeroConcepto) {

                    if (isset($conceptos[$numeroConcepto])) {
                        $formula = str_replace(
                            $placeholder,
                            $conceptos[$numeroConcepto]->idconcepto,
                            $formula
                        );
                    }
                }

                $concepto->update([
                    'formulaimportetotal' => $formula
                ]);
            }

            DB::connection('sqlsrv_dynamic')->statement("
                INSERT INTO nom10005 (
                    idempleado,
                    idconcepto
                )
                SELECT
                    e.idempleado,
                    c.idconcepto
                FROM nom10001 e
                CROSS JOIN nom10004 c
                WHERE c.numeroconcepto >= 4000 AND c.numeroconcepto <= 8002
                AND c.tipoconcepto = 'O'
            ");

            DB::commit();

            return response()->json([
                'code' => 200,
                'data' => 'ok',
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'code' => 500,
                'message' => 'Error al crear fórmulas',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
