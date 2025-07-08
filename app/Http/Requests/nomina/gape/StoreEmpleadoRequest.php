<?php

namespace App\Http\Requests\nomina\gape;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmpleadoRequest extends FormRequest
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
            'idempleado' => 'nullable|numeric',
            'estado_empleado' => 'nullable|boolean',
            'iddepartamento' => 'nullable|numeric',
            'idpuesto' => 'nullable|numeric',
            'idtipoperiodo' => 'required|numeric',
            'idturno' => 'nullable|numeric',

            'codigoempleado' => 'required|string|max:20|alpha_num', // ahora requerido
            'nombre' => 'nullable|string|max:60|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u',
            'apellidopaterno' => 'nullable|string|max:60|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u',
            'apellidomaterno' => 'nullable|string|max:60|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u',
            'nombrelargo' => 'nullable|string|max:120',

            'fechanacimiento' => 'nullable|date',
            'lugarnacimiento' => 'nullable|string|max:60|alpha',

            'curp' => [
                'nullable',
                'string',
                'size:18',
                'regex:/^[A-Z][AEIOU][A-Z]{2}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HM](AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TS|TL|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[A-Z\d]\d$/'
            ],

            'rfc' => [
                'nullable',
                'string',
                'regex:/^([A-ZÑ&]{3,4})(\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[A-Z\d]{2}([A\d])$/'
            ],

            'email' => 'nullable|email|max:100',

            'sucursalpagoelectronico' => 'nullable|alpha_num|max:50',
            'cuentapagoelectronico' => 'nullable|alpha_num|max:20',
            'ClabeInterbancaria' => 'nullable|digits_between:18,30|numeric',

            'tipocontrato' => 'required|string|max:10',
            'basecotizacionimss' => 'required|string|max:10',
            'tipoempleado' => 'required|string|max:10',

            'direccion' => 'nullable|alpha_num|max:60',
            'poblacion' => 'nullable|alpha_num|max:60',

            'sueldobaseliquidacion' => 'nullable|numeric|min:0.01',
            'fechaalta' => 'required|date', // ahora requerida

            'calculado' => ['required', 'boolean'],
            'afectado' => ['required', 'boolean'],
            'calculadoextraordinario' => ['required', 'boolean'],
            'afectadoextraordinario' => ['required', 'boolean'],
            'interfazcheqpaqw' => ['required', 'boolean'],
            'modificacionneto' => ['required', 'boolean'],
            'calculoptu' => ['required', 'boolean'],
            'calculoaguinaldo' => ['required', 'boolean'],
            'modificacionsalarioimss' => ['required', 'boolean'],
            'altaimss' => ['required', 'boolean'],
            'bajaimss' => ['required', 'boolean'],
            'cambiocotizacionimss' => ['required', 'boolean'],

            'Subcontratacion' => ['required', 'boolean'],
            'ExtranjeroSinCURP' => ['required', 'boolean'],
            'DiasVacTomadasAntesdeAlta' => ['required', 'numeric'],
            'DiasPrimaVacTomadasAntesdeAlta' => ['required', 'numeric'],
            'TipoSemanaReducida' => ['required', 'numeric'],
            'Teletrabajador' => ['required', 'numeric'],
        ];
    }

    public function messages()
    {
        return [
            'id_nomina_gape_empresa.required' => 'Selecciona una empresa.',
            'idtipoperiodo.required' => 'Selecciona un tipo de periodo.',
            'tipocontrato.required' => 'Selecciona un tipo de contrato.',
            'basecotizacionimss.required' => 'Selecciona una base de cotización.',
            'tipoempleado.required' => 'Selecciona el tipo de empleado.',
            'codigoempleado.required' => 'El código de empleado es obligatorio.',
            'curp.regex' => 'El CURP no tiene un formato válido.',
            'rfc.regex' => 'El RFC no tiene un formato válido.',
            'ClabeInterbancaria.digits_between' => 'La CLABE debe tener entre 18 y 30 dígitos.',
            'sueldobaseliquidacion.min' => 'Debe ser un número mayor a 0.',
            'fechaalta.required' => 'La fecha de alta es obligatoria.',
        ];
    }
}
