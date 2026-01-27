<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncidenciasHojasValidator
{
    /**
     * Valida que las hojas del Excel coincidan con los esquemas permitidos
     * según cliente, empresa y combinaciones seleccionadas.
     *
     * @throws \Exception
     */
    public function validate(
        Request $request,
        array $idEsquema,
        array $hojasExcel
    ): void {
        $idCliente = $request->idCliente;
        $idEmpresa = $request->idEmpresa;

        /**
         * 1️⃣ Obtener esquemas permitidos desde BD (configuración real)
         */
        $esquemasPermitidos = DB::table('nomina_gape_cliente_esquema_combinacion as ngcec')
            ->join(
                'nomina_gape_empresa_periodo_combinacion_parametrizacion as ngepcp',
                'ngcec.combinacion',
                '=',
                'ngepcp.id_nomina_gape_cliente_esquema_combinacion'
            )
            ->join(
                'nomina_gape_esquema as nge',
                'ngcec.id_nomina_gape_esquema',
                '=',
                'nge.id'
            )
            ->where('ngepcp.id_nomina_gape_cliente', $idCliente)
            ->where('ngepcp.id_nomina_gape_empresa', $idEmpresa)
            ->where('ngcec.id_nomina_gape_cliente', $idCliente)
            ->whereIn('ngcec.combinacion', $idEsquema)
            ->where('ngcec.orden', 1)
            ->distinct()
            ->pluck('nge.esquema')
            ->toArray();

        /**
         * 2️⃣ Mapa esquema → nombre de hoja
         */
        $mapaEsquemaConfig = [
            'Sueldo IMSS'          => 'SUELDO_IMSS',
            'Asimilados'           => 'ASIMILADOS',
            'Sindicato'            => 'SINDICATO',
            'Tarjeta facil'        => 'TARJETA_FACIL',
            'Gastos por comprobar' => 'GASTOS_POR_COMPROBAR',
        ];

        /**
         * 3️⃣ Convertir esquemas permitidos → hojas esperadas
         */
        $hojasEsperadas = [];

        foreach ($esquemasPermitidos as $esquema) {
            if (isset($mapaEsquemaConfig[$esquema])) {
                $hojasEsperadas[] = $mapaEsquemaConfig[$esquema];
            }
        }

        /**
         * 4️⃣ Comparaciones finales
         */
        $hojasNoPermitidas = array_diff($hojasExcel, $hojasEsperadas);
        $hojasFaltantes    = array_diff($hojasEsperadas, $hojasExcel);

        if (!empty($hojasNoPermitidas) || !empty($hojasFaltantes)) {
            throw new \Exception(json_encode([
                'hojas_no_permitidas' => array_values($hojasNoPermitidas),
                'hojas_faltantes'     => array_values($hojasFaltantes),
            ]));
        }
    }

    public function mapaEsquemas(
        Request $request,
        array $idEsquema,
    ) {

        $idCliente = $request->idCliente;
        $idEmpresa = $request->idEmpresa;

        $mapaEsquemas = DB::table('nomina_gape_cliente_esquema_combinacion as ngcec')
            ->join(
                'nomina_gape_empresa_periodo_combinacion_parametrizacion as ngepcp',
                'ngcec.combinacion',
                '=',
                'ngepcp.id_nomina_gape_cliente_esquema_combinacion'
            )
            ->join(
                'nomina_gape_esquema as nge',
                'ngcec.id_nomina_gape_esquema',
                '=',
                'nge.id'
            )
            ->where('ngepcp.id_nomina_gape_cliente', $idCliente)
            ->where('ngepcp.id_nomina_gape_empresa', $idEmpresa)
            ->where('ngcec.id_nomina_gape_cliente', $idCliente)
            ->whereIn('ngcec.combinacion', $idEsquema) // 👈 array del request
            ->where('ngcec.orden', 1)
            ->select(
                'ngcec.combinacion',
                'nge.id as id_esquema',
                'nge.esquema'
            )
            ->get();

        return $mapaEsquemas;
    }
}
