<?php

namespace App\Http\Services\Nomina\Export\Prenomina;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Http\Request;

class ExportPrenominaService
{
    public function loadSpreadsheet(string $path)
    {
        return $this->loadTemplate($path);
    }

    public function getDatosTotalesPorEsquema(array $config, PrenominaQueryService $queryService, $request): ?object
    {
        $queries = $config['queries'] ?? [];

        if (!isset($queries['totales'])) {
            return null;
        }
;
        return $queryService->getData($queries['totales'], $request);
    }

    public function fillSheetFromConfig($spreadsheet, array $config, PrenominaQueryService $queryService, $request): void
    {
        if (empty($spreadsheet) || empty($config)) {
            return;
        }

        $sheet = $this->getWorksheet($spreadsheet, $config['sheet_name']);

        $queries = $config['queries'];
        $titulosSeccion = $config['titulos'];

        /*
        |--------------------------------------------------------------------------
        | 1. CARGAR DATOS SOLO DE SECCIONES EXISTENTES
        |--------------------------------------------------------------------------
        */

        $dataDetalleEmpleado = collect(
            $queryService->getData($queries['detalle'], $request)
        )->map(fn($r) => (array)$r)->toArray();

        $indicesDetalleEmpleado = $this->getIndices($dataDetalleEmpleado);

        $datos   = [];
        $indices = [];

        foreach ($queries as $seccion => $queryName) {

            if (in_array($seccion, ['detalle', 'totales'])) {
                continue; // ⛔ IMPORTANTE
            }

            $rows = collect(
                $queryService->getData($queryName, $request)
            )->map(fn($r) => (array) $r)->toArray();

            $datos[$seccion]   = $rows;
            $indices[$seccion] = !empty($rows) ? array_keys($rows[0]) : [];
        }

        $datosTotales = isset($queries['totales'])
            ? $queryService->getData($queries['totales'], $request)
            : null;

        /*
        |--------------------------------------------------------------------------
        | 2. LIMPIAR COLUMNAS NO DESEADAS
        |--------------------------------------------------------------------------
        */

        $omitEmp = ['codigoempleado'];

        foreach ($indices as $sec => $cols) {
            $indices[$sec] = array_diff($cols, $omitEmp);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. DEFINIR COLUMNAS FIJAS (DETALLE EMPLEADO)
        |--------------------------------------------------------------------------
        */

        //$indicesDetalleEmpleado = $this->getIndices($datos[array_key_first($datos)] ?? []);

        $xlColumnaInicioDetalleEmpleado = 9;
        $xlColumnaFinDetalleEmpleado    = 16;


        $secciones = [];
        $colCursor = $xlColumnaFinDetalleEmpleado + 1;

        foreach ($indices as $sec => $cols) {

            if (empty($cols)) {
                continue;
            }

            $inicio = $colCursor;
            $colCursor += count($cols);
            $fin = $colCursor - 1;

            $secciones[] = [
                'key'    => $sec,
                'inicio' => $inicio,
                'fin'    => $fin,
                'titulo' => $titulosSeccion[$sec] ?? strtoupper($sec),
            ];

            $colCursor++; // columna separadora
        }

        /*
        dd([
            'row' => $colCursor,
        ]);
        */

        $xlTotalColumnas = $colCursor - 1;

        // Total solo de columnas dinámicas
        $xlTotalColumnasDinamicas = $xlTotalColumnas - $xlColumnaFinDetalleEmpleado;

        $sheet->insertNewColumnBefore($config['columna_inicio'], $xlTotalColumnasDinamicas);

        $xlFilaEncabezados = $config['fila_encabezado'];
        // Asigno última columna que existe, en este caso es la que está fija en el formato que es xlColumnaFinDetalleEmpleado por que las demás todavía no existen
        $xlColumnaActual = $xlColumnaFinDetalleEmpleado + 1;

        $xlColumnasExcluidas = [];

        foreach ($secciones as $sec) {

            foreach ($indices[$sec['key']] as $colName) {
                $col = Coordinate::stringFromColumnIndex($xlColumnaActual);
                $sheet->setCellValue($col . $xlFilaEncabezados, $colName);
                $xlColumnaActual++;
            }

            // separador
            $xlColumnasExcluidas[] = $xlColumnaActual;
            $xlColumnaActual++;
        }

        /*
        |--------------------------------------------------------------------------
        | 7. CONSTRUIR MATRIZ DE DATOS
        |--------------------------------------------------------------------------
        */
        // Construir matriz con todas las secciones
        $xlMatriz = [];

        foreach ($dataDetalleEmpleado as $objDetalleEmpleado) {

            $fila = [];

            // 🔹 DETALLE (solo una vez)
            foreach ($indicesDetalleEmpleado as $k) {
                $fila[] = $objDetalleEmpleado[$k] ?? null;
            }

            // 🔹 SECCIONES DINÁMICAS
            foreach ($secciones as $sec) {

                $rowSec = collect($datos[$sec['key']] ?? [])
                    ->firstWhere('codigoempleado', $objDetalleEmpleado['codigoempleado']);

                foreach ($indices[$sec['key']] as $k) {
                    $fila[] = $rowSec[$k] ?? null;
                }

                $fila[] = null; // separador
            }

            $xlMatriz[] = $fila;
        }

        /*
        |--------------------------------------------------------------------------
        | 8. INSERTAR FILAS
        |--------------------------------------------------------------------------
        */
        // Insertar filas nuevas sin sobrescribir
        $xlFilaInicioDatos = $config['fila_inicio_datos'];
        $xlFilaFinDatos = $xlFilaInicioDatos + count($xlMatriz) - 1;

        $sheet->insertNewRowBefore($config['fila_inicio_datos'], count($xlMatriz));

        // Insertar datos masivamente
        $sheet->fromArray($xlMatriz, null, $config['col_inicio']);


        if (!empty($datosTotales)) {

            $colPorcentaje = Coordinate::stringFromColumnIndex($xlTotalColumnas + 3);
            $sheet->setCellValue("{$colPorcentaje}" . ($xlFilaFinDatos + 7), $datosTotales->fee_porcentaje);

            $colTot = Coordinate::stringFromColumnIndex($xlTotalColumnas + 4);

            $sheet->setCellValue("{$colTot}" . ($xlFilaFinDatos + 4), $datosTotales->percepcion_bruta);
            $sheet->setCellValue("{$colTot}" . ($xlFilaFinDatos + 5), $datosTotales->costo_social);
            $sheet->setCellValue("{$colTot}" . ($xlFilaFinDatos + 6), $datosTotales->percepcion_bruta + $datosTotales->costo_social);
            $sheet->setCellValue("{$colTot}" . ($xlFilaFinDatos + 7), $datosTotales->fee);
            $sheet->setCellValue("{$colTot}" . ($xlFilaFinDatos + 10), $datosTotales->subtotal);
            $sheet->setCellValue("{$colTot}" . ($xlFilaFinDatos + 11), $datosTotales->iva);
            $sheet->setCellValue("{$colTot}" . ($xlFilaFinDatos + 12), $datosTotales->total);
        }

        /*
        |--------------------------------------------------------------------------
        | 10. AGRUPAR SECCIONES + TÍTULOS
        |--------------------------------------------------------------------------
        */
        $this->rowRangeGroup($sheet, 1, 9);


        /*
        dd([
            'row' => $secciones,
        ]);
        */


        foreach ($secciones as $sec) {

            $inicioCol = Coordinate::stringFromColumnIndex($sec['inicio']);
            $finCol    = Coordinate::stringFromColumnIndex($sec['fin']);

            // Agrupar columnas
            $this->columnRangeGroup($sheet, $inicioCol, $finCol);

            // Título
            $sheet->setCellValue("{$inicioCol}10", $sec['titulo']);

            // Merge del título
            $sheet->mergeCells("{$inicioCol}10:{$finCol}10");
        }

        // Es importante activar el resumen a la derecha
        $sheet->setShowSummaryRight(true);

        // Fila de totales
        $start = Coordinate::columnIndexFromString("I");
        $end   = $xlTotalColumnas;

        foreach (range($start, $end) as $i) {

            $colLetter = Coordinate::stringFromColumnIndex($i);

            // ❗ Si esta columna está en la lista de excluidas, saltar
            if (in_array($i, $xlColumnasExcluidas)) continue;

            $totalRow = $xlFilaFinDatos; // fila donde irá el total (lo moví +1 porque antes estaba pisando datos)
            $findRow = $xlFilaFinDatos - 1; // fila donde irá el total (lo moví +1 porque antes estaba pisando datos)

            // SUMA
            $sheet->setCellValue(
                "{$colLetter}{$totalRow}",
                "=SUM({$colLetter}12:{$colLetter}{$findRow})"
            );

            // Borde inferior doble
            $sheet->getStyle("{$colLetter}{$totalRow}")
                ->getBorders()->getBottom()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE);

            // Borde superior doble
            $sheet->getStyle("{$colLetter}{$totalRow}")
                ->getBorders()->getTop()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE);
        }

        // Ajustar AutoSize columnas A:H
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->calculateColumnWidths();
        }

        $sheet->getRowDimension(11)->setRowHeight(-1);

        // Asignar formato a todo como monto
        $this->asignarFormatoMonto($sheet, "I:" . Coordinate::stringFromColumnIndex($xlTotalColumnas - 1));

        // Asignar formato de cantidad
        $this->aplicarFormatoDinamico($sheet, 11, 12, $xlFilaFinDatos);

        // Estillos de las columnas de totales y netos
        $this->colorearColumnasPorEncabezado($sheet, 11);


        // Congeral la fila 11 y columna B++
        $sheet->freezePane($config['freeze_cell']);
    }

    public function fillTotalesSheet($spreadsheet, $esquemas, $config, $queryService, $request)
    {
        $bloque = [
            'col_inicio' => 'B',
            'fila_inicio' => 2,
            'col_fin' => 'D',
            'fila_fin' => 11,
            'ancho' => 3,   // columnas
            'alto'  => 10,  // filas
            'espacio' => 1, // columnas entre bloques
        ];

        $origenInicio = 'B2';
        $origenFin    = 'D11';

        $mapaEsquemaConfig = [
            'Sueldo IMSS'          => 'SUELDO_IMSS',
            'Asimilados'           => 'ASIMILADOS',
            'Sindicato'            => 'SINDICATO',
            'Tarjeta facil'        => 'TARJETA_FACIL',
            'Gastos por comprobar' => 'GASTOS_POR_COMPROBAR',
        ];

        $sheetTotales = $spreadsheet->getSheetByName('TOTALES');

        $bloqueLayout = [
            'titulo'         => 'B2',
            'percepcion_bruta'   => 'D3',
            'costo_social'   => 'D4',
            'base'           => 'D5',
            'fee'           => 'D6',
            'fee_porcentaje' => 'C6',
            'subtotal'       => 'D9',
            'iva'            => 'D10',
            'total'          => 'D11',
        ];

        $colBaseIdx = Coordinate::columnIndexFromString('B');

        // 🔢 Acumulador global
        $totalesGlobales = [
            'percepcion_bruta'  => 0,
            'costo_social'  => 0,
            'base'          => 0,
            'fee'           => 0,
            'subtotal'      => 0,
            'iva'           => 0,
            'total'         => 0,
        ];

        $index = 0;

        foreach ($esquemas as $i => $esquema) {

            $clave = $mapaEsquemaConfig[$esquema->esquema] ?? null;
            if (!$clave || !isset($config[$clave])) {
                continue;
            }

            $configHoja = $config[$clave];

            $colActual = Coordinate::stringFromColumnIndex(
                $colBaseIdx + ($i * ($bloque['ancho'] + $bloque['espacio']))
            );

            // 1️⃣ Copiar bloque
            $this->copiarBloque(
                $sheetTotales,
                $origenInicio,
                $origenFin,
                "{$colActual}2"
            );

            // 2️⃣ Obtener combinación
            $combo = collect($request->combinaciones)
                ->firstWhere('id_esquema', (int)$esquema->id);

            if (!$combo) {
                continue;
            }

            // 🔹 request contextual por hoja
            $requestCombo = new Request(array_merge(
                $request->all(),
                $combo,
                [
                    'id_nomina_gape_esquema' => $esquema->id_nomina_gape_esquema,
                ]
            ));

            // 2️⃣ Obtener datos
            $datos = $this->getDatosTotalesPorEsquema($configHoja, $queryService, $requestCombo);

            /*
            dd([
                'row' => $datos,
            ]);
            */
            if (!$datos) {
                continue;
            }

            // 3️⃣ Llenar valores
            $this->llenarBloqueTotales(
                $sheetTotales,
                "{$colActual}2",
                $bloqueLayout,
                $datos,
                "DESGLOSE DE COBRO {$esquema->esquema}",
                'B2'
            );

            // 6️⃣ Acumular
            foreach ($totalesGlobales as $k => $v) {
                $totalesGlobales[$k] += $datos->$k ?? 0;
            }

            $index++;
        }

        // 🟦 BLOQUE TOTAL GENERAL
        if ($index > 0) {

            $colTotal = Coordinate::stringFromColumnIndex(
                $colBaseIdx + ($index * ($bloque['ancho'] + $bloque['espacio']))
            );

            $this->copiarBloque($sheetTotales, $origenInicio, $origenFin, "{$colTotal}2");

            $this->llenarBloqueTotales(
                $sheetTotales,
                "{$colTotal}2",
                $bloqueLayout,
                (object)$totalesGlobales,
                'DESGLOSE DE COBRO TOTAL',
                'B2'
            );
        }

        // Ajustar AutoSize columnas A:Z
        foreach (range('A', 'Z') as $col) {
            $sheetTotales->getColumnDimension($col)->setAutoSize(true);
            $sheetTotales->calculateColumnWidths();
        }
    }

    /* ===========================================================
     *     MÉTODOS PRIVADOS PARA MANTENER CÓDIGO LIMPIO
     * ===========================================================
     */

    private function llenarBloqueTotales(
        Worksheet $sheet,
        string $destinoInicio,   // ej: "F2" (colActual + filaInicio)
        array $layout,           // celdas en coordenadas del template (B2..D11)
        ?object $datos,
        string $titulo,
        string $origenInicio = 'B2' // anchor del template
    ): void {

        if (!$datos) {
            $datos = (object)[];
        }

        // Anchor origen (template)
        [$colOriAnchor, $rowOriAnchor] = Coordinate::coordinateFromString($origenInicio);
        $colOriAnchorIdx = Coordinate::columnIndexFromString($colOriAnchor);

        // Anchor destino (donde pegaste el bloque)
        [$colDesAnchor, $rowDesAnchor] = Coordinate::coordinateFromString($destinoInicio);
        $colDesAnchorIdx = Coordinate::columnIndexFromString($colDesAnchor);

        foreach ($layout as $key => $cellRef) {

            // cellRef es del template, por ejemplo "D3"
            [$colRef, $rowRef] = Coordinate::coordinateFromString($cellRef);
            $colRefIdx = Coordinate::columnIndexFromString($colRef);

            // offset relativo al template
            $deltaCol = $colRefIdx - $colOriAnchorIdx;   // D - B => 2 (queda en la 3ra col del bloque)
            $deltaRow = $rowRef - $rowOriAnchor;         // 3 - 2 => 1

            // destino final
            $colFinal = Coordinate::stringFromColumnIndex($colDesAnchorIdx + $deltaCol);
            $rowFinal = $rowDesAnchor + $deltaRow;

            if ($key === 'titulo') {
                $sheet->setCellValue("{$colFinal}{$rowFinal}", $titulo);
                continue;
            }

            // escribir valores SOLO si existen
            if (isset($datos->$key)) {
                $sheet->setCellValue("{$colFinal}{$rowFinal}", $datos->$key);
            }
        }
    }

    private function copiarBloque(
        Worksheet $sheet,
        string $origenInicio,
        string $origenFin,
        string $destinoInicio
    ): void {

        [$colOriIni, $rowOriIni] = Coordinate::coordinateFromString($origenInicio);
        [$colOriFin, $rowOriFin] = Coordinate::coordinateFromString($origenFin);
        [$colDesIni, $rowDesIni] = Coordinate::coordinateFromString($destinoInicio);

        $colOriIniIdx = Coordinate::columnIndexFromString($colOriIni);
        $colOriFinIdx = Coordinate::columnIndexFromString($colOriFin);
        $colDesIniIdx = Coordinate::columnIndexFromString($colDesIni);

        // 1️⃣ Copiar celdas y estilos
        for ($row = $rowOriIni; $row <= $rowOriFin; $row++) {
            for ($col = $colOriIniIdx; $col <= $colOriFinIdx; $col++) {

                $colOri = Coordinate::stringFromColumnIndex($col);
                $colDes = Coordinate::stringFromColumnIndex(
                    $colDesIniIdx + ($col - $colOriIniIdx)
                );

                $cellOri = "{$colOri}{$row}";
                $cellDes = "{$colDes}" . ($rowDesIni + ($row - $rowOriIni));

                // 👉 COPIAR VALOR (incluye textos fijos)
                $sheet->setCellValue($cellDes, $sheet->getCell($cellOri)->getValue());

                // 👉 COPIAR ESTILO
                $sheet->duplicateStyle(
                    $sheet->getStyle($cellOri),
                    $cellDes
                );
            }
        }

        // 2️⃣ Copiar celdas combinadas
        foreach ($sheet->getMergeCells() as $merged) {

            [$mStart, $mEnd] = explode(':', $merged);
            [$mColStart, $mRowStart] = Coordinate::coordinateFromString($mStart);
            [$mColEnd, $mRowEnd] = Coordinate::coordinateFromString($mEnd);

            $mColStartIdx = Coordinate::columnIndexFromString($mColStart);
            $mColEndIdx   = Coordinate::columnIndexFromString($mColEnd);

            // Verificar si el merge pertenece al bloque origen
            if (
                $mRowStart >= $rowOriIni && $mRowEnd <= $rowOriFin &&
                $mColStartIdx >= $colOriIniIdx && $mColEndIdx <= $colOriFinIdx
            ) {

                $newStartCol = Coordinate::stringFromColumnIndex(
                    $colDesIniIdx + ($mColStartIdx - $colOriIniIdx)
                );
                $newEndCol = Coordinate::stringFromColumnIndex(
                    $colDesIniIdx + ($mColEndIdx - $colOriIniIdx)
                );

                $newStartRow = $rowDesIni + ($mRowStart - $rowOriIni);
                $newEndRow   = $rowDesIni + ($mRowEnd - $rowOriIni);

                $sheet->mergeCells("{$newStartCol}{$newStartRow}:{$newEndCol}{$newEndRow}");
            }
        }
    }

    private function getIndices(array $data): array
    {
        return !empty($data) && is_array($data[0])
            ? array_keys($data[0])
            : [];
    }

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
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if (!$sheet) {
            throw new \Exception("La hoja '{$sheetName}' no existe en el archivo Excel.");
        }

        return $sheet;
    }

    function rowRangeGroup($sheet, int $startRow, int $endRow)
    {
        for ($row = $startRow; $row <= $endRow; $row++) {
            $sheet->getRowDimension($row)
                ->setOutlineLevel(1)
                ->setVisible(false)
                ->setCollapsed(false);
        }
    }

    function columnRangeGroup($sheet, string $startCol, string $endCol)
    {
        $startIndex = Coordinate::columnIndexFromString($startCol);
        $endIndex   = Coordinate::columnIndexFromString($endCol);

        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);

            $sheet->getColumnDimension($colLetter)
                ->setOutlineLevel(1)
                ->setVisible(false)
                ->setCollapsed(true);
        }
    }

    public function asignarFormatoMonto($sheet, string $rango)
    {
        // Aplicar formato al rango completo (esto sí acepta A:A, A:D, etc.)
        $sheet->getStyle($rango)
            ->getNumberFormat()
            ->setFormatCode('_-$* #,##0.00_-;-$* #,##0.00_-;_-$* "-"??_-;_-@_-');

        // Si el rango contiene ":" entonces hay varias columnas
        if (strpos($rango, ':') !== false) {

            [$colInicio, $colFin] = explode(':', $rango);

            // Convertir letras a índices (A=1, B=2...)
            $startIndex = Coordinate::columnIndexFromString($colInicio);
            $endIndex   = Coordinate::columnIndexFromString($colFin);

            // Recorrer todas las columnas del rango
            for ($i = $startIndex; $i <= $endIndex; $i++) {
                $col = Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        } else {
            // Rango de una sola columna
            $sheet->getColumnDimension($rango)->setAutoSize(true);
        }
    }

    public function aplicarFormatoDinamico($sheet, $headerRow, $startRow, $endRow)
    {
        $highestCol = $sheet->getHighestColumn();
        $highestIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

        for ($c = 1; $c <= $highestIndex; $c++) {

            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);

            // Leer encabezado original
            $header = (string) $sheet->getCell("{$col}{$headerRow}")->getValue();

            // Normalizar: minúsculas y acentos fuera
            $h = strtolower(trim($header));
            $h = str_replace(
                ['á', 'é', 'í', 'ó', 'ú'],
                ['a', 'e', 'i', 'o', 'u'],
                $h
            );

            // Rango de la columna
            $range = "{$col}{$startRow}:{$col}{$endRow}";

            // --- DETECTAR CANTIDAD ---
            if (
                str_contains($h, 'cantidad') ||
                str_contains($h, 'dias') ||
                str_contains($h, 'faltas') ||
                str_contains($h, 'incapacidad')
            ) {
                $sheet->getStyle($range)->getNumberFormat()
                    ->setFormatCode('#,##0');

                $sheet->getColumnDimension($col)->setAutoSize(false);
                $sheet->getColumnDimension($col)->setWidth(10);

                $sheet->getStyle($col)->getAlignment()->setHorizontal('center');
                continue;
            }
        }
    }

    public function colorearColumnasPorEncabezado($sheet, int $headerRow)
    {
        $highestCol = $sheet->getHighestColumn();
        $highestIndex = Coordinate::columnIndexFromString($highestCol);

        for ($i = 1; $i <= $highestIndex; $i++) {

            $col = Coordinate::stringFromColumnIndex($i);
            $header = (string) $sheet->getCell("{$col}{$headerRow}")->getValue();

            // Normalizar
            $h = strtolower(trim($header));
            $h = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $h);

            // Validar si está vacío
            if ($h === '') continue;

            // --- TOTAL → VERDE ---
            if (str_contains($h, 'total')) {
                $sheet->getStyle("{$col}{$headerRow}")
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('92D050'); // verde claro
                continue;
            }

            // --- NETO → NEGRO ---
            if (str_contains($h, 'neto')) {
                $sheet->getStyle("{$col}{$headerRow}")
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('000000'); // negro
                $sheet->getStyle("{$col}{$headerRow}")
                    ->getFont()->getColor()->setARGB('FFFFFF'); // texto blanco
                continue;
            }
        }
    }
}
