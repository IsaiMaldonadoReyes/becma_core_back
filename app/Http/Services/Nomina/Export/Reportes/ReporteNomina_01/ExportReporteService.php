<?php

namespace App\Http\Services\Nomina\Export\Reportes\ReporteNomina_01;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

use Illuminate\Support\Facades\DB;


use App\Models\nomina\default\Periodo;

use App\Http\Services\Nomina\Export\Reportes\ReporteNomina_01\ConfigFormatoService;

use App\Http\Services\Core\HelperService;

class ExportReporteService
{

    protected QueryServiceImss $queryServiceImss;
    protected QueryServiceAsimilado $queryServiceAsimilado;
    protected QueryServiceExcedente $queryServiceExcedente;
    protected HelperService $helper;

    public function __construct(
        QueryServiceImss $queryServiceImss,
        QueryServiceAsimilado $queryServiceAsimilado,
        QueryServiceExcedente $queryServiceExcedente,
        HelperService $helper

    ) {
        $this->queryServiceImss = $queryServiceImss;
        $this->queryServiceAsimilado = $queryServiceAsimilado;
        $this->queryServiceExcedente = $queryServiceExcedente;
        $this->helper = $helper;
    }

    public function preProcessData(array $data)
    {
        $configService = ConfigFormatoService::getConfig();

        $spreadsheet = $this->loadSpreadsheet($configService['reporte']['path']);

        $rowsReporte = [];

        foreach ($data as $item) {
            $queryName = 'data_' . $item['base_fee'];


            $esquemaPrincipal = trim(explode('+', $item['combinacion'])[0]);

            $conexion = $this->helper->getConexionDatabaseNGE($item['id_nomina_gape_empresa'], 'Nom');

            switch ($esquemaPrincipal) {

                case 'Sueldo IMSS':
                    if (empty($conexion)) {
                        break;
                    }

                    $this->helper->setDatabaseConnection($conexion, $conexion->nombre_base);

                    $periodosEntreFechas = Periodo::select('idperiodo', 'numeroperiodo')
                        ->where('idtipoperiodo', $item['idtipoperiodo'])
                        ->where('fechaPago', '>=', $item['fecha_inicial'])
                        ->where('fechaPago', '<=', $item['fecha_final'])
                        ->where('afectado', '=', 1)
                        ->get();


                    foreach ($periodosEntreFechas as $periodo) {

                        $itemPeriodo = $item;
                        $itemPeriodo['id_periodo'] = $periodo->idperiodo;
                        $itemPeriodo['numeroperiodo'] = $periodo->numeroperiodo;




                        $dataRaw = $this->queryServiceImss->getData(
                            $queryName,
                            $itemPeriodo
                        );

                        /*
                        dd([
                            'dataRaw' => $dataRaw,
                        ]);

                        */

                        if (!empty($dataRaw)) {
                            $rowsReporte[] = $this->mapRowReporte(
                                $itemPeriodo,
                                (array) $dataRaw
                            );
                        }
                    }

                    break;

                case 'Asimilados':

                    $conexion = $this->helper->getConexionDatabaseNGE($item['id_nomina_gape_empresa'], 'Nom');
                    if (empty($conexion)) {
                        break;
                    }

                    $this->helper->setDatabaseConnection($conexion, $conexion->nombre_base);

                    $periodosEntreFechas = Periodo::select('idperiodo', 'numeroperiodo')
                        ->where('idtipoperiodo', $item['idtipoperiodo'])
                        ->where('fechaPago', '>=', $item['fecha_inicial'])
                        ->where('fechaPago', '<=', $item['fecha_final'])
                        ->where('afectado', '=', 1)
                        ->get();

                    foreach ($periodosEntreFechas as $periodo) {

                        $itemPeriodo = $item;
                        $itemPeriodo['id_periodo'] = $periodo->idperiodo;
                        $itemPeriodo['numeroperiodo'] = $periodo->numeroperiodo;

                        $dataRaw = $this->queryServiceAsimilado->getData(
                            $queryName,
                            $itemPeriodo
                        );

                        if (!empty($dataRaw)) {
                            $rowsReporte[] = $this->mapRowReporte(
                                $itemPeriodo,
                                (array) $dataRaw
                            );
                        }
                    }


                    break;

                case 'Sindicato':
                case 'Tarjeta facil':
                case 'Gastos por comprobar':

                    $itemPeriodo = $item;
                    $itemPeriodo['esquema'] = $esquemaPrincipal;

                    $dataRaw = $this->queryServiceExcedente->getData(
                        'data_01',
                        $itemPeriodo
                    );

                    foreach ($dataRaw as $row) {
                        $rowsReporte[] = $this->mapRowReporte(
                            $itemPeriodo,
                            (array) $row
                        );
                    }
                    break;

                default:
                    throw new \Exception(
                        "Esquema no soportado: {$esquemaPrincipal}"
                    );
            }
        }

        $this->fillReporteNomina(
            $spreadsheet,
            $configService['reporte'],
            $rowsReporte
        );

        return $spreadsheet;
    }

    private function mapRowReporte(array $item, array $row): array
    {
        return [
            // A a G
            $this->getNombrePeriodo($item),                    // A Periodo
            '',                                                // B Analista
            $item['nombretipoperiodo'] ?? '',                  // C Tipo de periodo
            $item['cliente'] ?? '',                            // D Razón social
            $item['cliente'] ?? '',                            // E Nombre comercial
            $this->getTipoNomina($item['combinacion'] ?? ''),  // F Tipo de nómina
            $this->getEsquemaFacturacion($item['base_fee'] ?? ''), // G Esquema facturación
            // I a Y
            $row['Total Empleados'] ?? 0,          // I
            $row['Salarios Brutos'] ?? 0,          // J
            $row['Sindicato'] ?? 0,                // K
            $row['Asimilados Neto'] ?? 0,          // L
            $row['Asimilados Bruto'] ?? 0,         // M
            $row['Tarjeta facil'] ?? 0,            // N
            $row['Gastos por comprobar'] ?? 0,     // O
            $row['Base de facturacion'] ?? 0,      // P
            $row['ISN 3%'] ?? 0,                   // Q
            $row['IMSS Patronal'] ?? 0,            // R
            $row['Cuota Obrera de SMG'] ?? 0,      // S
            $row['RCV Patronal'] ?? 0,             // T
            $row['Infonavit Patronal'] ?? 0,       // U
            $row['Total de cuotas patronales'] ?? 0, // V
            $row['Fee'] ?? 0,                      // W
            $row['Subtotal'] ?? 0,                 // X
            $row['IVA'] ?? 0,                      // Y
            $row['Total Factura'] ?? 0,            // Z si también la ocupas
        ];
    }

    public function loadSpreadsheet(string $path)
    {
        return $this->loadTemplate($path);
    }

    private function fillReporteNomina($spreadsheet, array $config, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $sheet = $this->getWorksheet($spreadsheet, $config['sheet_name']);

        $filaInicio = 2;
        $filaActual = $filaInicio;

        $filasTotalesGrupo = [];

        $rowsAgrupados = collect($rows)->groupBy(function ($row) {
            return $row[2] ?? 'SIN TIPO'; // C: Tipo de periodo
        });

        foreach ($rowsAgrupados as $tipoPeriodo => $rowsGrupo) {
            $inicioGrupo = $filaActual;

            foreach ($rowsGrupo as $row) {
                $sheet->fromArray([$row], null, "A{$filaActual}");
                $filaActual++;
            }

            $finGrupo = $filaActual - 1;

            // Total por grupo
            $sheet->setCellValue("G{$filaActual}", "TOTAL {$tipoPeriodo}");

            foreach (range('H', 'Y') as $col) {
                $sheet->setCellValue(
                    "{$col}{$filaActual}",
                    "=SUM({$col}{$inicioGrupo}:{$col}{$finGrupo})"
                );
            }

            $sheet->getStyle("A{$filaActual}:Y{$filaActual}")
                ->getFont()
                ->setBold(true);

            $filasTotalesGrupo[] = $filaActual;

            $filaActual++;
        }

        // Total general
        $sheet->setCellValue("G{$filaActual}", "TOTAL GENERAL");

        foreach (range('H', 'Y') as $col) {
            $referencias = array_map(
                fn($fila) => "{$col}{$fila}",
                $filasTotalesGrupo
            );

            $sheet->setCellValue(
                "{$col}{$filaActual}",
                '=SUM(' . implode(',', $referencias) . ')'
            );
        }

        $sheet->getStyle("A{$filaActual}:Y{$filaActual}")
            ->getFont()
            ->setBold(true);
    }

    private function fillReporteNomina3($spreadsheet, array $config, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $sheet = $this->getWorksheet($spreadsheet, $config['sheet_name']);

        $filaInicio = 2;
        $filaActual = $filaInicio;

        $rowsAgrupados = collect($rows)->groupBy(function ($row) {
            return $row[2] ?? 'SIN TIPO'; // columna C: Tipo de periodo
        });

        foreach ($rowsAgrupados as $tipoPeriodo => $rowsGrupo) {
            $inicioGrupo = $filaActual;

            foreach ($rowsGrupo as $row) {
                $sheet->fromArray([$row], null, "A{$filaActual}");
                $filaActual++;
            }

            $finGrupo = $filaActual - 1;

            $sheet->setCellValue("G{$filaActual}", "TOTAL {$tipoPeriodo}");

            foreach (range('H', 'Y') as $col) {
                $sheet->setCellValue(
                    "{$col}{$filaActual}",
                    "=SUM({$col}{$inicioGrupo}:{$col}{$finGrupo})"
                );
            }

            $sheet->getStyle("A{$filaActual}:Y{$filaActual}")
                ->getFont()
                ->setBold(true);

            $filaActual++;
        }
    }

    private function fillReporteNomina2($spreadsheet, array $config, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $sheet = $this->getWorksheet($spreadsheet, $config['sheet_name']);

        $filaInicio = 2;
        $colInicio = 'A';

        $sheet->fromArray($rows, null, $colInicio . $filaInicio);

        $filaFin = $filaInicio + count($rows) - 1;
        $filaTotal = $filaFin + 1;

        $sheet->setCellValue("G{$filaTotal}", 'TOTAL');

        foreach (range('H', 'Y') as $col) {
            $sheet->setCellValue(
                "{$col}{$filaTotal}",
                "=SUM({$col}{$filaInicio}:{$col}{$filaFin})"
            );
        }

        $sheet->getStyle("G{$filaTotal}:Y{$filaTotal}")
            ->getFont()
            ->setBold(true);
    }

    private function getNombrePeriodo(array $item): string
    {
        if (!empty($item['numeroperiodo'])) {
            return $item['numeroperiodo'];
        }

        if (!empty($item['nombretipoperiodo'])) {
            return $item['nombretipoperiodo'];
        }

        return $item['id_periodo'] ?? '';
    }

    private function getTipoNomina(string $combinacion): string
    {
        return strtoupper(str_replace(' + ', ' - ', $combinacion));
    }

    private function getEsquemaFacturacion(string $baseFee): string
    {
        return match ($baseFee) {
            '01' => 'PERCEPCIONES BRUTAS',
            '02' => 'PERCEPCIONES BRUTAS + CARGA SOCIAL',
            '03' => 'NETO',
            '04' => 'NETO + CARGA SOCIAL',
            '05' => 'FEE NETO + BRUTO + CARGA SOCIAL',
            default => '',
        };
    }

    public function fillSheetFromConfig($spreadsheet, array $config, array $data, array $dataHeader = []): void
    {
        if (empty($data)) {
            return;
        }

        $sheet = $this->getWorksheet($spreadsheet, $config['sheet_name']);

        $sheet->setCellValue('B2', $dataHeader['cliente'] ?? '');
        $sheet->setCellValue('B4', $dataHeader['empresa'] ?? '');
        $sheet->setCellValue('B5', $dataHeader['ejercicio'] ?? '');
        $sheet->setCellValue('B6', $dataHeader['tipoPeriodo'] ?? '');
        $sheet->setCellValue('B7', $dataHeader['periodo'] ?? '');

        $matrix = $this->buildMatrix($data);

        $this->insertRows($sheet, $config['fila_insert'], count($matrix));
        $this->fillMatrix($sheet, $matrix, $config['col_inicio']);
        $this->autosizeColumns($sheet, $config['auto_cols'][0], $config['auto_cols'][1]);
        $this->applyGrouping($sheet, $config['group_rows']);
        $this->applyFreezePane($sheet, $config['freeze_cell']);
    }

    /* ===========================================================
     *     MÉTODOS PRIVADOS PARA MANTENER CÓDIGO LIMPIO
     * ===========================================================
     */

    private function loadTemplate(string $path)
    {
        $fullPath = storage_path("app/public/" . $path);

        if (!file_exists($fullPath)) {
            throw new \Exception("La plantilla no existe: {$fullPath}");
        }

        return IOFactory::load($fullPath);
    }

    private function getWorksheet($spreadsheet, string $sheetName): Worksheet
    {
        /*
        dd([
            'spreadsheet' => $spreadsheet,
            'sheetName' => $sheetName,
        ]);
        */

        $sheet = $spreadsheet->getSheetByName($sheetName);

        if (!$sheet) {
            throw new \Exception("La hoja '{$sheetName}' no existe en el archivo Excel.");
        }

        return $sheet;
    }

    private function buildMatrix(array $data): array
    {
        // Obtener orden de columnas del primer registro
        $columnKeys = array_keys($data[0]);

        $matrix = [];

        foreach ($data as $row) {
            $fila = [];

            // Agregar columnas en el orden correcto
            foreach ($columnKeys as $key) {
                $fila[] = $row[$key] ?? null;
            }

            // Agregar columna vacía como separador
            $fila[] = null;

            $matrix[] = $fila;
        }

        return $matrix;
    }

    private function insertRows(Worksheet $sheet, int $filaInsert, int $cantidad)
    {
        $sheet->insertNewRowBefore($filaInsert, $cantidad);
    }

    private function fillMatrix(Worksheet $sheet, array $data, string $startCell)
    {
        $sheet->fromArray($data, null, $startCell);
    }

    private function autosizeColumns(Worksheet $sheet, string $colStart, string $colEnd)
    {
        foreach (range($colStart, $colEnd) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function applyGrouping(Worksheet $sheet, array $groupRows)
    {
        [$start, $end] = $groupRows;

        for ($i = $start; $i <= $end; $i++) {
            $sheet->getRowDimension($i)->setOutlineLevel(1);
            $sheet->getRowDimension($i)->setCollapsed(true);
        }

        $sheet->setShowSummaryRight(true);
    }

    private function applyFreezePane(Worksheet $sheet, string $startCell)
    {
        /**
         * El freezePane funciona así:
         * freezePane("C10") congela todo lo anterior a la celda C10:
         * → columnas A y B
         * → filas 1 a 9
         */

        $sheet->freezePane($startCell);
    }
}
