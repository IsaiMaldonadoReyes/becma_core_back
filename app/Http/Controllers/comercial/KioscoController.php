<?php

namespace App\Http\Controllers\comercial;

use Carbon\Carbon;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\comercial\kiosco\Plantel;
use App\Models\comercial\kiosco\CodigoPostal;
use App\Models\comercial\kiosco\Catalogo;

use Illuminate\Support\Facades\DB;

class KioscoController extends Controller
{
    public function empresas()
    {
        try {
            $plantel = Plantel::select(
                'id',
                'numero',
                'nombre'
            )
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $plantel,
            ], 200);
        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function listaCodigoPostal(Request $request)
    {
        try {
            $validated = $request->validate([
                'codigopostal' => 'required|string|regex:/^\d{1,10}$/'
            ]);
            $codigopostalInput = $validated['codigopostal'];

            $codigopostal = CodigoPostal::select(
                'd_codigo'
            )
                ->where('d_codigo', 'like', $codigopostalInput . '%')
                ->limit(10)
                ->distinct()
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $codigopostal,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'code' => 422,
                'message' => 'Datos de entrada inválidos',
                'errors' => $ve->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function catalogos()
    {
        try {
            $usoCfdi = Catalogo::select(
                'codigo',
                'descrip',
            )
                ->where('tipo_cat', '=', 'uso_cfdi')
                ->orderBy('codigo')
                ->get();

            $regimen = Catalogo::select(
                'codigo',
                'descrip',
            )
                ->where('tipo_cat', '=', 'regimen')
                ->orderBy('codigo')
                ->get();

            return response()->json([
                'code' => 200,
                'uso_cfdi' => $usoCfdi,
                'regimen' => $regimen,
            ], 200);
        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function direccion(Request $request)
    {
        try {
            $codigoCP = CodigoPostal::select(
                'd_mnpio',
                'd_estado',
                'd_ciudad',
            )
                ->groupByRaw('d_mnpio, d_estado,d_ciudad')
                ->where('d_codigo', '=', $request->codigopostal)
                ->distinct()
                ->first();

            $asentamiento = CodigoPostal::select(
                'd_asenta'
            )
                ->where('d_codigo', '=', $request->codigopostal)
                ->select('d_asenta')
                ->orderBy('d_asenta')
                ->get();

            return response()->json([
                'code' => 200,
                'direccion' => $codigoCP,
                'asentamientos' => $asentamiento,
            ], 200);
        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function validarTicket(Request $request)
    {
        try {
            // 🔹 Normaliza cualquier formato (ISO o dd/MM/yyyy)
            if ($request->has('fecha')) {
                if (str_contains($request->fecha, '/')) {
                    // viene como 05/10/2025
                    $fechaNormalizada = Carbon::createFromFormat('d/m/Y', $request->fecha)->format('Y-m-d');
                } else {
                    // viene como ISO 8601 (2025-10-05T06:00:00.000Z)
                    $fechaNormalizada = Carbon::parse($request->fecha)->format('Y-m-d');
                }

                $request->merge(['fecha' => $fechaNormalizada]);
            }

            $plantel = Plantel::select(
                'id',
                'numero',
                'nombre',
                'base_contpaq'
            )
                ->where('id', '=', $request->id)
                ->first();

            $recibo = DB::table('recibo')
                ->select(
                    'recibo_encabezado.id AS idEncabezado',
                    'recibo_encabezado.id_doc AS idReciboContpaq',
                    'recibo.id AS idRecibo',
                    'recibo.fecha AS fechaRecibo',
                    'recibo_encabezado.pendiente AS pendienteEncabezado',
                    'recibo_encabezado.error AS errorEncabezado'
                )
                ->leftJoin('recibo_movimiento', 'recibo.id', '=', 'recibo_movimiento.id_recibo')
                ->leftJoin('recibo_encabezado', 'recibo_movimiento.id_recibo_encabezado', '=', 'recibo_encabezado.id')
                ->where('recibo.folio', '=', $request->folio)
                ->where('recibo.monto', '=', $request->importe)
                ->where('recibo.id_plantel', '=', $request->id)
                ->whereRaw('CONVERT(VARCHAR, recibo.fecha, 23) = ?', [$request->fecha])
                ->first();

            /*
            $sql = $recibo->toSql();
            foreach ($recibo->getBindings() as $binding) {
                $binding = is_numeric($binding) ? $binding : "'{$binding}'";
                $sql = preg_replace('/\?/', $binding, $sql, 1);
            }

            dd($sql);
            */

            $codigo = 0;

            if (!$recibo) {
                $codigo = 2;
            } else {

                $fechaRecibo = Carbon::parse($recibo->fechaRecibo);
                $fechaActual = Carbon::now();

                // Logica para determinar el codigo a regresar

                // si el recibo esta facturado y pendiente = 2, sin error
                if ($recibo->idReciboContpaq !== null && $recibo->pendienteEncabezado == "2" && $recibo->idRecibo !== null) {
                    if (
                        $fechaRecibo->isSameMonth($fechaActual)
                        && $fechaRecibo->isSameYear($fechaActual)
                    ) {
                        $dataDocumento = DB::select("
                            SELECT *
                            FROM $plantel->base_contpaq.dbo.admDocumentos
                            WHERE CIDDOCUMENTO = $recibo->idReciboContpaq
                                AND CCANCELADO = 1");

                        if (!empty($dataDocumento)) {
                            DB::table('recibo_encabezado')->where('id', '=', $recibo->idEncabezado)->delete();
                            $codigo = 3;
                        } else {
                            $codigo = 1;
                        }
                    } else {
                        $codigo = 1;
                    }
                } else if (
                    $recibo->idReciboContpaq === null
                    && $recibo->idRecibo !== null
                    && $fechaRecibo->isSameMonth($fechaActual)
                    && $fechaRecibo->isSameYear($fechaActual)
                ) {
                    $codigo = 3;
                } else if (
                    $recibo->idReciboContpaq === null
                    && $recibo->idRecibo !== null
                    && !$fechaRecibo->isSameMonth($fechaActual)
                ) {
                    $codigo = 6;
                } else if ($recibo->idReciboContpaq !== null && $recibo->pendienteEncabezado == 1) {
                    $codigo = 4;
                }
            }

            // NOTA: solo si el codigo es 1 y es el primer intento dentro del formulario, lo va a redireccionar al pdf, en caso contrario le indicara al usuario que ya fue facturado

            // codigo => 1 : el ticket se encuentra facturado y se procede a mostrar el pdf
            // codigo => 2 : no existe el ticket
            // codigo => 3 : el ticket es valido y no ha sido agregado a una factura
            // codigo => 4 : el ticket se encuentra dentro de una factura pero esta pendiente o en espera de tratar de ser facturado
            // codigo => 6 : el ticket se encuentra fuera del mes actual

            $dataQry = array();
            $dataQry = array(
                'codigo' => $codigo,
                'idRecibo' => $recibo->idRecibo ?? null,
                'idReciboEncabezado' => $recibo->idEncabezado ?? null,
            );

            return response()->json([
                'code' => 200,
                'data' => $dataQry,
            ], 200);
        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function upsetTicket(Request $request)
    {
        try {
            date_default_timezone_set('America/Mexico_City');
            $date = now()->format('m/d/Y h:i:s a');

            // Validar estructura del request
            if (!$request->has('tickets') || empty($request->tickets)) {
                return response()->json([
                    'code' => 400,
                    'message' => 'No se recibieron tickets válidos.',
                ], 400);
            }

            $tickets = $request->tickets;
            $data = (object) $request->dataModel;

            // Ordenar los tickets por monto descendente
            $keys = array_column($tickets, 'importe');
            array_multisort($keys, SORT_DESC, $tickets);



            if (str_contains($tickets[0]['fechaFormato'], '/')) {
                // viene como 05/10/2025
                $fechaNormalizada = Carbon::createFromFormat('d/m/Y', $tickets[0]['fechaFormato'])->format('Y-m-d');
            } else {
                // viene como ISO 8601 (2025-10-05T06:00:00.000Z)
                $fechaNormalizada = Carbon::parse($tickets[0]['fechaFormato'])->format('Y-m-d');
            }

            $tickets[0]['fechaFormato'] = $fechaNormalizada;

            // Buscar el primer recibo (de mayor monto)
            $recibo = DB::table('recibo')
                ->where([
                    ['folio', '=', $tickets[0]['folio']],
                    ['monto', '=', $tickets[0]['importe']],
                    ['id_plantel', '=', $tickets[0]['id']],
                ])
                ->whereRaw('CONVERT(VARCHAR, fecha, 23) = ?', [$tickets[0]['fechaFormato']])
                ->first();



            if (!$recibo) {
                return response()->json([
                    'code' => 404,
                    'message' => 'No se encontró el recibo principal.',
                ], 404);
            }

            // Insertar encabezado
            $idReciboEncabezado = DB::table('recibo_encabezado')->insertGetId([
                'id_plantel' => $data->id_sucursal,
                'forma_pago' => $recibo->forma_pago ?? null,
                'ref_pago' => $recibo->ref_pago ?? null,
                'rfc' => $data->rfc,
                'razon_social' => $data->razon_social,
                'correo' => $data->correo,
                'codigo_postal' => $data->codigo_postal,
                'estado' => $data->estado,
                'ciudad' => $data->ciudad,
                'municipio' => $data->municipio,
                'colonia' => $data->colonia,
                'calle' => $data->calle,
                'no_ext' => $data->numero_exterior,
                'no_int' => $data->numero_interior,
                'uso_cfdi' => $data->uso_cfdi,
                'regimen' => $data->regimen_fiscal,
                'pendiente' => 1,
                'error' => null,
                'observaciones' => $data->observaciones,
                'fecha_peticion' => $date,
            ]);

            // Insertar movimientos asociados
            foreach ($tickets as $ticketDetalle) {


                if (str_contains($ticketDetalle['fechaFormato'], '/')) {
                    // viene como 05/10/2025
                    $fechaNormalizada = Carbon::createFromFormat('d/m/Y', $ticketDetalle['fechaFormato'])->format('Y-m-d');
                } else {
                    // viene como ISO 8601 (2025-10-05T06:00:00.000Z)
                    $fechaNormalizada = Carbon::parse($ticketDetalle['fechaFormato'])->format('Y-m-d');
                }

                $ticketDetalle['fechaFormato'] = $fechaNormalizada;

                $reciboMultiple = DB::table('recibo')
                    ->where([
                        ['folio', '=', $ticketDetalle['folio']],
                        ['monto', '=', $ticketDetalle['importe']],
                        ['id_plantel', '=', $ticketDetalle['id']],
                    ])
                    ->whereRaw('CONVERT(VARCHAR, fecha, 23) = ?', [$ticketDetalle['fechaFormato']])
                    ->first();

                if ($reciboMultiple) {
                    DB::table('recibo_movimiento')->insert([
                        'id_recibo_encabezado' => $idReciboEncabezado,
                        'id_recibo' => $reciboMultiple->id,
                    ]);
                }
            }

            return response()->json([
                'code' => 200,
                'message' => 'Ticket actualizado correctamente.',
                'data' => [
                    'idReciboEncabezado' => $idReciboEncabezado,
                ],
            ], 200);
        } catch (\Exception $e) {
            // Manejo de errores generales
            return response()->json([
                'code' => 500,
                'message' => 'Error al validar los datos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function descargarPdf(Request $request)
    {
        try {
            // Validar parámetros requeridos
            if (!$request->has('idReciboEncabezado')) {
                return response()->json([
                    'code' => 400,
                    'message' => 'Falta parámetro obligatorio.',
                ], 400);
            }

            // Obtener recibo y plantel según el origen
            $recibo = DB::table('recibo_encabezado')->where('id', $request->idReciboEncabezado)->first();


            if (!$recibo) {
                return response()->json([
                    'code' => 404,
                    'message' => 'No se encontró el recibo especificado.',
                ], 404);
            }

            // Obtener plantel
            $plantel = DB::table('plantel')->where('id', $recibo->id_plantel)->first();
            if (!$plantel) {
                return response()->json([
                    'code' => 404,
                    'message' => 'No se encontró sucursal asociada al recibo.',
                ], 404);
            }

            // Obtener configuración general
            $configuracion = DB::table('configuracion')->first();
            if (!$configuracion) {
                return response()->json([
                    'code' => 500,
                    'message' => 'No se encontró la configuración del sistema.',
                ], 500);
            }

            // Construir ruta del archivo PDF
            $filePath = $configuracion->ruta_empresas . "\\" . $plantel->base_contpaq . $recibo->url_pdf;
            $filePath = str_replace("\\", "/", $filePath); // Normalizar separadores

            // Verificar existencia del archivo
            if (!file_exists($filePath)) {
                return response()->json([
                    'code' => 404,
                    'message' => 'El archivo PDF no existe en la ruta especificada.',
                    'ruta' => $filePath,
                ], 404);
            }

            // Convertir el PDF a base64
            $b64Doc = base64_encode(file_get_contents($filePath));

            // Retornar archivo codificado
            return response()->json([
                'code' => 200,
                'message' => 'Archivo PDF obtenido correctamente.',
                'fileName' => basename($filePath),
                'base64' => $b64Doc,
            ], 200);
        } catch (\Exception $e) {
            // Captura de errores no previstos
            return response()->json([
                'code' => 500,
                'message' => 'Error al descargar el PDF.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function descargarXml(Request $request)
    {
        try {
            // Validar parámetros requeridos
            if (!$request->has('idReciboEncabezado')) {
                return response()->json([
                    'code' => 400,
                    'message' => 'Falta parámetro obligatorio.',
                ], 400);
            }

            // Obtener recibo según el origen
            $recibo = DB::table('recibo_encabezado')->where('id', $request->idReciboEncabezado)->first();

            if (!$recibo) {
                return response()->json([
                    'code' => 404,
                    'message' => 'No se encontró el recibo especificado.',
                ], 404);
            }

            // Obtener plantel asociado
            $plantel = DB::table('plantel')->where('id', $recibo->id_plantel)->first();
            if (!$plantel) {
                return response()->json([
                    'code' => 404,
                    'message' => 'No se encontró la sucursal asociada al recibo.',
                ], 404);
            }

            // Obtener configuración del sistema
            $configuracion = DB::table('configuracion')->first();
            if (!$configuracion) {
                return response()->json([
                    'code' => 500,
                    'message' => 'No se encontró la configuración del sistema.',
                ], 500);
            }

            // Construir ruta completa al archivo XML
            $filePath = $configuracion->ruta_empresas . "\\" . $plantel->base_contpaq . $recibo->url_xml;
            $filePath = str_replace("\\", "/", $filePath); // Normalizar separadores

            // Verificar si el archivo existe
            if (!file_exists($filePath)) {
                return response()->json([
                    'code' => 404,
                    'message' => 'El archivo XML no existe en la ruta especificada.',
                    'ruta' => $filePath,
                ], 404);
            }

            // Leer y codificar el archivo XML en base64
            $b64Doc = base64_encode(file_get_contents($filePath));

            // Retornar archivo en formato JSON
            return response()->json([
                'code' => 200,
                'message' => 'Archivo XML obtenido correctamente.',
                'fileName' => basename($filePath),
                'base64' => $b64Doc,
            ], 200);
        } catch (\Exception $e) {
            // Captura de errores no previstos
            return response()->json([
                'code' => 500,
                'message' => 'Error al descargar el XML.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getEstatusTicket(Request $request)
    {
        try {
            // Validar parámetro obligatorio
            if (!$request->has('idReciboEncabezado')) {
                return response()->json([
                    'code' => 400,
                    'message' => 'Falta el parámetro obligatorio.',
                ], 400);
            }

            // Buscar el recibo en la tabla recibo_encabezado
            $recibo = DB::table('recibo_encabezado')
                ->select('id', 'pendiente', 'error')
                ->where('id', $request->idReciboEncabezado)
                ->first();

            // Devolver respuesta exitosa
            return response()->json([
                'code' => 200,
                'message' => 'Recibo obtenido correctamente.',
                'data' => $recibo,
            ], 200);
        } catch (\Exception $e) {
            // Captura de errores inesperados
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener el recibo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteTicket(Request $request)
    {
        try {
            // Validar parámetro obligatorio
            if (!$request->has('idRecibo')) {
                return response()->json([
                    'code' => 400,
                    'message' => 'Falta el parámetro obligatorio: idRecibo.',
                ], 400);
            }

            $idRecibo = $request->idReciboEncabezado;

            // Verificar si el recibo existe
            $recibo = DB::table('recibo_encabezado')->where('id', $idRecibo)->first();
            if (!$recibo) {
                return response()->json([
                    'code' => 404,
                    'message' => 'No se encontró el recibo especificado.',
                ], 404);
            }

            // Eliminar posibles movimientos relacionados
            DB::table('recibo_movimiento')
                ->where('id_recibo_encabezado', $idRecibo)
                ->delete();

            // Eliminar el encabezado principal
            DB::table('recibo_encabezado')
                ->where('id', $idRecibo)
                ->delete();

            return response()->json([
                'code' => 200,
                'message' => 'Recibo eliminado correctamente.',
                'deleted_id' => $idRecibo,
            ], 200);
        } catch (\Exception $e) {
            // Captura de errores
            return response()->json([
                'code' => 500,
                'message' => 'Error al eliminar el recibo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCliente(Request $request)
    {
        try {
            // Buscar el plantel
            $plantel = Plantel::select('id', 'numero', 'nombre', 'base_contpaq')
                ->where('id', '=', $request->id_sucursal)
                ->first();

            if (!$plantel) {
                return response()->json([
                    'code' => 404,
                    'message' => 'Sucursal no encontrada',
                ], 404);
            }

            // Construir consulta dinámica
            $sql = "
                    SELECT TOP 1
                        c.CRFC,
                        c.CRAZONSOCIAL,
                        ISNULL(d.CCIUDAD,'') AS CCIUDAD,
                        ISNULL(d.CESTADO,'') AS CESTADO,
                        ISNULL(d.CCODIGOPOSTAL,'') AS CCODIGOPOSTAL,
                        ISNULL(d.CNUMEROEXTERIOR,'') AS CNUMEROEXTERIOR,
                        ISNULL(d.CNUMEROINTERIOR,'') AS CNUMEROINTERIOR,
                        ISNULL(d.CNOMBRECALLE,'') AS CNOMBRECALLE,
                        ISNULL(d.CCOLONIA,'') AS CCOLONIA,
                        ISNULL(d.CMUNICIPIO,'') AS CMUNICIPIO,
                        ISNULL(c.CEMAIL1,'') AS CEMAIL1,
                        c.CUSOCFDI,
                        c.CREGIMFISC
                    FROM {$plantel->base_contpaq}.dbo.admClientes AS c
                    LEFT JOIN {$plantel->base_contpaq}.dbo.admDomicilios AS d
                        ON c.CIDCLIENTEPROVEEDOR = d.CIDCATALOGO
                    WHERE c.CRFC = ?
                    AND d.CTIPOCATALOGO = 1
                    AND d.CTIPODIRECCION = 0
                ";

            // Ejecutar consulta con parámetros (para evitar inyección SQL)
            $cliente = DB::select($sql, [$request->rfc]);

            if (empty($cliente)) {
                return response()->json([
                    'code' => 404,
                    'message' => 'Cliente no encontrado',
                ], 404);
            }

            // Preparar respuesta estructurada
            $data = [
                'CRAZONSOCIAL'    => $cliente[0]->CRAZONSOCIAL,
                'CCIUDAD'         => $cliente[0]->CCIUDAD,
                'CESTADO'         => $cliente[0]->CESTADO,
                'CCODIGOPOSTAL'   => $cliente[0]->CCODIGOPOSTAL,
                'CNUMEROEXTERIOR' => $cliente[0]->CNUMEROEXTERIOR,
                'CNUMEROINTERIOR' => $cliente[0]->CNUMEROINTERIOR,
                'CNOMBRECALLE'    => $cliente[0]->CNOMBRECALLE,
                'CCOLONIA'        => $cliente[0]->CCOLONIA,
                'CMUNICIPIO'      => $cliente[0]->CMUNICIPIO,
                'CEMAIL1'         => $cliente[0]->CEMAIL1,
                'CUSOCFDI'         => $cliente[0]->CUSOCFDI,
                'CREGIMFISC'         => $cliente[0]->CREGIMFISC,
            ];

            return response()->json([
                'code' => 200,
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos del cliente',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
