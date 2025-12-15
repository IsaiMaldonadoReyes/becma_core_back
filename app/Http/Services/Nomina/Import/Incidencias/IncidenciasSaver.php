<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use App\Models\nomina\GAPE\NominaGapeIncidencia;
use App\Models\nomina\GAPE\NominaGapeIncidenciaDetalle;
use App\Models\nomina\default\Empleado;

class IncidenciasSaver
{
    public function guardarMaestro($request)
    {
        return NominaGapeIncidencia::create([
            'estado'                  => 1,
            'id_nomina_gape_cliente'  => $request->idCliente,
            'id_nomina_gape_empresa'  => $request->idEmpresa,
            'id_tipo_periodo'         => $request->idTipoPeriodo,
            'id_periodo'              => $request->idPeriodo,
        ]);
    }

    public function guardarDetalle($sheet, $row, $incidenciaId)
    {
        $codigo = trim($sheet->getCell("A{$row}")->getValue());
        $empleado = Empleado::where('codigoempleado', $codigo)->first();

        return NominaGapeIncidenciaDetalle::create([
            'estado' => 1,
            'id_nomina_gape_incidencia' => $incidenciaId,
            'id_empleado' => $empleado->idempleado ?? null,

            'cantidad_faltas'             => floatval($sheet->getCell("M{$row}")->getValue()),
            'cantidad_vacaciones'         => floatval($sheet->getCell("N{$row}")->getValue()),
            'horas_extra_doble'           => floatval($sheet->getCell("O{$row}")->getValue()),
            'cantidad_prima_vacacional'   => floatval($sheet->getCell("P{$row}")->getValue()),

            'cantidad_prima_dominical'    => floatval($sheet->getCell("Q{$row}")->getValue()),
            'cantidad_dias_festivos'      => floatval($sheet->getCell("R{$row}")->getValue()),
            'comision'                    => floatval($sheet->getCell("S{$row}")->getValue()),
            'bono'                        => floatval($sheet->getCell("T{$row}")->getValue()),

            'horas_extra_doble_cantidad'  => floatval($sheet->getCell("U{$row}")->getValue()),
            'horas_extra_triple_cantidad' => floatval($sheet->getCell("V{$row}")->getValue()),
            'premio_puntualidad'          => floatval($sheet->getCell("W{$row}")->getValue()),

            'pago_adicional'              => floatval($sheet->getCell("X{$row}")->getValue()),
            'descuento'                   => floatval($sheet->getCell("Y{$row}")->getValue()),

            'descuento_aportacion_caja_ahorro'  => floatval($sheet->getCell("Z{$row}")->getValue()),
            'descuento_prestamo_caja_ahorro'    => floatval($sheet->getCell("AA{$row}")->getValue()),

            'infonavit'                 => floatval($sheet->getCell("AB{$row}")->getValue()),
            'fonacot'                   => floatval($sheet->getCell("AC{$row}")->getValue()),

            'cantidad_incapacidad'      => floatval($sheet->getCell("AD{$row}")->getValue()),
            'incapacidad_dias'          => trim((string) $sheet->getCell("AE{$row}")->getValue()),
            'cantidad_dias_retroactivos'      => floatval($sheet->getCell("AG{$row}")->getValue()),


            'codigo_empleado' => $codigo,
        ]);
    }
}
