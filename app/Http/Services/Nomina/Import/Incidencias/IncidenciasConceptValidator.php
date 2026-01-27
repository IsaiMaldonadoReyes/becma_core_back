<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\nomina\default\MovimientosPDOVigente;

class IncidenciasConceptValidator
{
    public function validate(Worksheet $sheet, int $row, $request): IncidenciasValidationBag
    {
        $issues = new IncidenciasValidationBag();

        // ----------------------------------------------------------------
        // 1) Recibir datos previos del RowValidator
        // ----------------------------------------------------------------

        // PERO: el importer debe pasarnos los datos del RowValidator

        if (!$request->attributes->has('_validator_row')) {
            return $issues;
        }

        $rowData = $request->attributes->get('_validator_row');  // recuperamos los datos

        $empleado = $rowData['empleado'] ?? null;
        $tieneDatos = $rowData['tieneDatos'] ?? false;

        if (!$empleado || !$tieneDatos) {
            // no hay nada que validar
            return $issues;
        }

        $idempleado = $empleado->idempleado;
        $idPeriodo = $request->idPeriodo;

        // ----------------------------------------------------------------
        // 2) Leer valores Excel (O, P)
        // ----------------------------------------------------------------

        $valorAniosPrimaVacacional = intval($sheet->getCell("O{$row}")->getValue()); // concepto 20
        $valorDiasPrimaVacacional = intval($sheet->getCell("P{$row}")->getValue()); // concepto 20
        $valorDiasRetroactivo = intval($sheet->getCell("AG{$row}")->getValue()); // concepto 16


        $valorPrimaVacacional =
            ($valorAniosPrimaVacacional > 0)
            ? $valorAniosPrimaVacacional
            : (($valorDiasPrimaVacacional > 0) ? $valorDiasPrimaVacacional : 0);

        // Mismo mapa que tu código original
        $conceptosAValidar = [
            20 => ['valor' => $valorPrimaVacacional,    'col' => 'O | P', 'descripcion' => 'Prima de vacaciones a tiempo'],
            16 => ['valor' => $valorDiasRetroactivo,    'col' => 'AG', 'descripcion' => 'Retroactivo'],
        ];

        // ----------------------------------------------------------------
        // 3) Obtener conceptos capturados (nom10008)
        // ----------------------------------------------------------------
        $conceptosYaExistentes = MovimientosPDOVigente::from('nom10008 AS movs')
            ->join('nom10004 AS con', 'movs.idconcepto', '=', 'con.idconcepto')
            ->where('movs.idempleado', $idempleado)
            ->where('movs.idperiodo', $idPeriodo)
            ->where('con.tipoconcepto', 'P')
            ->whereIn('con.numeroconcepto', [20, 16])
            ->pluck('con.numeroconcepto')
            ->toArray();

        // ----------------------------------------------------------------
        // 4) Validar duplicados: mismo comportamiento que tu código
        // ----------------------------------------------------------------
        foreach ($conceptosAValidar as $numConcepto => $item) {

            $valor = $item['valor'];
            $col   = $item['col'];
            $desc  = $item['descripcion'];

            if ($valor > 0) {
                if (in_array($numConcepto, $conceptosYaExistentes)) {
                    $issues->add(
                        "El concepto '{$desc}' (concepto {$numConcepto}) ya existe en la nómina del empleado y no puede duplicarse.",
                        $row,
                        $col,
                        'nomina',
                        'conceptosRepetidos',
                        $valor
                    );
                }
            }
        }

        return $issues;
    }
}
