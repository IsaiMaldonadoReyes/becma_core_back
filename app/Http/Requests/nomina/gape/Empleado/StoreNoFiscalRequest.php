<?php

namespace App\Http\Requests\nomina\gape\Empleado;

use Illuminate\Foundation\Http\FormRequest;

class StoreNoFiscalRequest extends FormRequest
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
            'id_nomina_gape_empresa' => 'required|numeric',
            'id_nomina_gape_cliente' => 'nullable|numeric',
            'id_nomina_gape_esquema' => 'nullable|numeric',
            'fiscal' => 'nullable|boolean',
            'estado_empleado' => 'nullable|boolean',
            'fechaalta' => 'required|date',
            'apellidopaterno' => 'nullable|string|max:60|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u',
            'apellidomaterno' => 'nullable|string|max:60|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u',
            'nombre' => 'nullable|string|max:60|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u',
            'cuentacw' => 'required|string|max:31',
            'ClabeInterbancaria' => 'nullable|digits_between:10,30|numeric',
            'codigopostal' => 'nullable|string|max:5',

            'campoextra1' => 'required|string',
            'ccampoextranumerico1' => 'nullable|numeric|min:0',
            'ccampoextranumerico2' => 'nullable|numeric|min:0',
            'ccampoextranumerico3' => 'nullable|numeric|min:0',
            'ccampoextranumerico4' => 'nullable|numeric|min:0',
        ];
    }

    public function messages()
    {
        return [
            // 🏢 Empresa y cliente
            'id_nomina_gape_empresa.required' => 'Selecciona una empresa para continuar.',
            'id_nomina_gape_empresa.numeric' => 'La empresa seleccionada no es válida.',
            'id_nomina_gape_cliente.numeric' => 'El cliente seleccionado no es válido.',

            'id_nomina_gape_esquema.numeric' => 'El esquema seleccionado no es válido.',

            // 💰 Fiscal
            'fiscal.boolean' => 'El campo "Fiscal" debe ser verdadero o falso.',

            // 📅 Fechas
            'fechaalta.required' => 'Debes ingresar la fecha de alta del empleado.',
            'fechaalta.date' => 'La fecha de alta no tiene un formato válido (usa YYYY-MM-DD).',
            'campoextra1.required' => 'Debes ingresar la fecha de alta en el sistema GAPE.',

            // 👤 Datos personales
            'apellidopaterno.string' => 'El apellido paterno debe contener solo letras.',
            'apellidopaterno.max' => 'El apellido paterno no debe superar los 60 caracteres.',
            'apellidopaterno.regex' => 'El apellido paterno solo puede incluir letras y espacios.',

            'apellidomaterno.string' => 'El apellido materno debe contener solo letras.',
            'apellidomaterno.max' => 'El apellido materno no debe superar los 60 caracteres.',
            'apellidomaterno.regex' => 'El apellido materno solo puede incluir letras y espacios.',

            'nombre.string' => 'El nombre debe contener solo letras.',
            'nombre.max' => 'El nombre no debe superar los 60 caracteres.',
            'nombre.regex' => 'El nombre solo puede incluir letras y espacios.',

            // 🧾 RFC / Cuenta CW
            'cuentacw.required' => 'El RFC del empleado es obligatorio.',
            'cuentacw.string' => 'El RFC debe ser una cadena de texto.',
            'cuentacw.max' => 'El RFC no debe superar los 31 caracteres.',

            // 💵 Sueldos
            'ccampoextranumerico1.required' => 'Debes ingresar el sueldo real del empleado.',
            'ccampoextranumerico1.numeric' => 'El sueldo real debe ser un número válido.',
            'ccampoextranumerico1.min' => 'El sueldo real no puede ser negativo.',

            'ccampoextranumerico2.required' => 'Debes ingresar el sueldo IMSS GAPE.',
            'ccampoextranumerico2.numeric' => 'El sueldo IMSS GAPE debe ser un número válido.',
            'ccampoextranumerico2.min' => 'El sueldo IMSS GAPE no puede ser negativo.',

            // 🏦 CLABE interbancaria
            'ClabeInterbancaria.numeric' => 'La CLABE interbancaria solo puede contener números.',
            'ClabeInterbancaria.digits_between' => 'La CLABE interbancaria debe tener entre 10 y 30 dígitos.',

            // 📮 Código postal
            'codigopostal.string' => 'El código postal debe ser texto.',
            'codigopostal.max' => 'El código postal no debe superar los 5 caracteres.',
        ];
    }
}
