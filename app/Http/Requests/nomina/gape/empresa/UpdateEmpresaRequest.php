<?php

namespace App\Http\Requests\nomina\gape\empresa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->route('id'); // 👈 obtiene el ID del parámetro de ruta

        return [
            'razon_social' => 'required|string|max:255',

            // 🔹 RFC único por cliente, pero ignorando el mismo registro
            'rfc' => [
                'required',
                'string',
                'max:13',
                'min:12',
                Rule::unique('nomina_gape_empresa', 'rfc')
                    ->where(
                        fn($query) =>
                        $query->where('id_nomina_gape_cliente', $this->id_nomina_gape_cliente)
                    )
                    ->ignore($empresaId, 'id'),
            ],

            'id_nomina_gape_cliente' => 'required|integer|exists:nomina_gape_cliente,id',

            // 🔹 Solo obligatorio si fiscal = true
            'id_empresa_database' => 'nullable|integer|exists:empresa_database,id',

            // 🔹 Código interno único por cliente, pero ignorando el mismo registro
            'codigo_interno' => [
                'required',
                'string',
                'max:50',
                Rule::unique('nomina_gape_empresa', 'codigo_interno')
                    ->where(
                        fn($query) =>
                        $query->where('id_nomina_gape_cliente', $this->id_nomina_gape_cliente)
                    )
                    ->ignore($empresaId, 'id'),
            ],
            'estado' => 'required|boolean',

            'correo_notificacion' => 'required|email|max:255',

            // 🔹 Solo requeridos si la empresa es no fiscal
            'mascara_codigo' => 'nullable|string',
            'codigo_inicial' => 'nullable|string',
            'codigo_actual' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'razon_social.required' => 'La razón social es obligatoria.',
            'razon_social.string' => 'La razón social debe ser texto.',
            'razon_social.max' => 'La razón social no debe exceder 255 caracteres.',

            'rfc.required' => 'El RFC es obligatorio.',
            'rfc.string' => 'El RFC debe ser texto.',
            'rfc.max' => 'El RFC no debe exceder 13 caracteres.',
            'rfc.min' => 'El RFC debe tener al menos 12 caracteres.',
            'rfc.unique' => 'El RFC ya está registrado para este cliente.',

            'id_nomina_gape_cliente.required' => 'Debe seleccionar un cliente.',
            'id_nomina_gape_cliente.integer' => 'El identificador del cliente no es válido.',
            'id_nomina_gape_cliente.exists' => 'El cliente seleccionado no existe.',

            'id_empresa_database.required_if' => 'Debe seleccionar una empresa base cuando la empresa es fiscal.',
            'id_empresa_database.integer' => 'El identificador de la empresa debe ser un número.',
            'id_empresa_database.exists' => 'La empresa base seleccionada no existe.',

            'codigo_interno.required' => 'El código interno es obligatorio.',
            'codigo_interno.string' => 'El código interno debe ser texto.',
            'codigo_interno.max' => 'El código interno no debe exceder 50 caracteres.',
            'codigo_interno.unique' => 'El código interno ya está registrado para este cliente.',

            'estado.required' => 'Debe especificar si la empresa está activa o no.',
            'estado.boolean' => 'El campo estado debe ser verdadero o falso.',

            'correo_notificacion.required' => 'El correo de notificación es obligatorio.',
            'correo_notificacion.email' => 'El formato del correo electrónico no es válido.',
            'correo_notificacion.max' => 'El correo no debe exceder 255 caracteres.',

            'mascara_codigo.required_if' => 'Debe ingresar la máscara de código.',
            'codigo_inicial.required_if' => 'Debe ingresar el código inicial.',
            'codigo_actual.required_if' => 'Debe ingresar el código actual.',
        ];
    }
}
