<?php

namespace App\Http\Requests\nomina\gape\empresa;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmpresaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'razon_social' => 'required|string|max:255',
            'rfc' => 'required|string|max:13|min:12',
            'id_nomina_gape_cliente' => 'required|integer',
            'id_empresa_database' => 'required_if:fiscal,true|nullable|integer',
            'codigo_interno' => 'required|string|max:50',
            'fiscal' => 'required|boolean',
            'correo_notificacion' => 'required|email|max:255',
        ];
    }

    /**
     * Mensajes personalizados para las validaciones
     */
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

            'id_nomina_gape_cliente.required' => 'Debe seleccionar un cliente.',
            'id_nomina_gape_cliente.integer' => 'El identificador del cliente no es válido.',

            'id_empresa_database.required_if' => 'Debe seleccionar una empresa base cuando la empresa es fiscal.',
            'id_empresa_database.integer' => 'El identificador de la empresa debe ser un número.',

            'fiscal.required' => 'Debe especificar si la empresa es fiscal o no.',
            'fiscal.boolean' => 'El campo fiscal debe ser verdadero o falso.',

            'codigo_interno.required' => 'El código interno es obligatorio.',
            'codigo_interno.string' => 'El código interno debe ser texto.',
            'codigo_interno.max' => 'El código interno no debe exceder 50 caracteres.',

            'correo_notificacion.required' => 'El correo de notificación es obligatorio.',
            'correo_notificacion.email' => 'El formato del correo electrónico no es válido.',
            'correo_notificacion.max' => 'El correo no debe exceder 255 caracteres.',
        ];
    }
}
