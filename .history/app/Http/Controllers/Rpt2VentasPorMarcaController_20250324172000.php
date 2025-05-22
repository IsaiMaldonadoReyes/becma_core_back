<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class Rpt2VentasPorMarcaController extends Controller
{
    public function grafica2Labels(Request $request)
    {
        $datosEmpresa = Empresa::find($request->idEmpresa);

        $this->setDatabaseConnection($datosEmpresa);

        $labels = "DECLARE @startDate DATE, @endDate DATE;
                    SET @startDate = '$request->fechaInicial';  
                    SET @endDate = '$request->fechaFinal';    

                    
                    IF DATEDIFF(MONTH, @startDate, @endDate) > 0
                    BEGIN
                        
                        WITH Meses AS (
                            SELECT @startDate AS Fecha
                            UNION ALL
                            SELECT DATEADD(MONTH, 1, Fecha)
                            FROM Meses
                            WHERE DATEADD(MONTH, 1, Fecha) <= @endDate
                        )
                        SELECT DISTINCT Format(Fecha, 'MMMM', 'es-ES') AS labels,
                                        Month(Fecha)                   AS mesNumero
                        FROM   Meses
                        ORDER  BY Month(Fecha)
                        OPTION (MAXRECURSION 12);
                    END
                    
                    ELSE IF DATEDIFF(DAY, @startDate, @endDate) > 1
                    BEGIN
                        
                        SELECT CONVERT(VARCHAR(10), @startDate, 23) AS labels
                        UNION ALL
                        SELECT CONVERT(VARCHAR(10), DATEADD(DAY, 7, @startDate), 23)
                        UNION ALL
                        SELECT CONVERT(VARCHAR(10), DATEADD(DAY, 14, @startDate), 23)
                        UNION ALL
                        SELECT CONVERT(VARCHAR(10), DATEADD(DAY, 21, @startDate), 23)
                    END
                    
                    ELSE
                    BEGIN
                        
                        SELECT CONVERT(VARCHAR(10), @startDate, 23) AS labels
                    END
                    ";
        $resultados = DB::select(DB::raw($labels));

        return $resultados;
    }
}
