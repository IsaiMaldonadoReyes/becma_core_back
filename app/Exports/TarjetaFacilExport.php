<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class TarjetaFacilExport implements FromCollection, WithHeadings, WithEvents
{
    use Exportable;

    protected Collection $data;
    protected string $cuentaCargo;
    protected string $nombreOrdenante;

    public function __construct(Collection $data, string $nombreOrdenante)
    {
        $this->data = $data;
        $this->nombreOrdenante = $nombreOrdenante;
    }

    public function collection(): Collection
    {
        return $this->data
            ->map(fn($row) => [
                $row['nombre'],
                $row['ap'], // PATERNO
                $row['am'], // MATERNO
                $row['tarjetafacil'],
                (float) $row['importe'],
            ])
            ->values();
    }

    public function headings(): array
    {
        return [
            'NOMBRE',
            'PATERNO',
            'MATERNO',
            'TARJETA FACIL',
            'IMPORTE',
        ];
    }

    /**
     * 🔹 Manipulación fina de la hoja
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $sheet = $event->sheet->getDelegate();

                $sheet->insertNewRowBefore(1, 2);
                /*
                |--------------------------------------------------------------------------
                | ENCABEZADOS SUPERIORES
                |--------------------------------------------------------------------------
                */

                $sheet->setCellValue('A1', 'PAGADORA:');
                $sheet->setCellValue('B1', 'TARJETA FACIL');

                $sheet->setCellValue('A2', 'CLIENTE');
                $sheet->setCellValue('B2', $this->nombreOrdenante);

                $sheet->getStyle('A1:A2')->getFont()->setBold(true);
                $sheet->getStyle('B1:B2')->getFont()->setBold(true);


                // Laravel Excel pone headings en fila 1, los movemos a la 3

                /*
                |--------------------------------------------------------------------------
                | FORMATO DE ENCABEZADOS
                |--------------------------------------------------------------------------
                */

                $sheet->getStyle('A3:E3')->getFont()->setBold(true);

                /*
                |--------------------------------------------------------------------------
                | TOTAL CON FÓRMULA
                |--------------------------------------------------------------------------
                */

                $lastDataRow = $sheet->getHighestRow();
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue("D{$totalRow}", 'TOTAL');
                $sheet->setCellValue("E{$totalRow}", "=SUM(E4:E{$lastDataRow})");

                $sheet->getStyle("D{$totalRow}:E{$totalRow}")
                    ->getFont()
                    ->setBold(true);

                /*
                |--------------------------------------------------------------------------
                | FORMATO MONEDA
                |--------------------------------------------------------------------------
                */

                $sheet->getStyle("E4:E{$totalRow}")
                    ->getNumberFormat()
                    ->setFormatCode('"$"#,##0.00');

                /*
                |--------------------------------------------------------------------------
                | AJUSTES VISUALES
                |--------------------------------------------------------------------------
                */

                $sheet->freezePane('A4');
                $sheet->setAutoFilter("A3:E{$lastDataRow}");

                foreach (range('A', 'E') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            }
        ];
    }

    public function title(): string
    {
        return 'Tarjeta facil';
    }
}
