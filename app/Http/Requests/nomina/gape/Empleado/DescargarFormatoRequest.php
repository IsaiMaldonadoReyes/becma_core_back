<?php

namespace App\Http\Requests\nomina\gape\Empleado;

use Illuminate\Foundation\Http\FormRequest;

class DescargarFormatoRequest extends FormRequest
{
    public function rules()
    {
        return [
            'empresa_id' => 'required|exists:empresas,id',
            'fiscal' => 'required|boolean',
        ];
    }
}