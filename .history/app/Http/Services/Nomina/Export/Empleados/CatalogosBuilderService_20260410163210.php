<?php 
namespace App\Http\Services\Nomina\Export\Empleados;

class CatalogoBuilderService
{
    public function injectCatalogs(array $cellsConfig): array
    {
        foreach ($cellsConfig as &$column) {

            if ($column['tipoDeColumna'] === 'combo') {

                switch ($column['key']) {

                    case 'tipoContrato':
                        $column['options'] = TipoContrato::pluck('nombre')->toArray();
                        break;

                    case 'departamento':
                        $column['options'] = Departamento::pluck('nombre')->toArray();
                        break;
                }
            }
        }

        return $cellsConfig;
    }
}