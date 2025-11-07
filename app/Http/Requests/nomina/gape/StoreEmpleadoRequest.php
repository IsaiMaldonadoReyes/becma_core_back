<?php

namespace App\Http\Requests\nomina\gape;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmpleadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 🟢 Campos requeridos (esenciales)
            'id_nomina_gape_empresa' => 'required|numeric',
            'idtipoperiodo' => 'required|numeric',
            'tipocontrato' => 'required|string|max:10',
            'basecotizacionimss' => 'required|string|max:10',
            'tipoempleado' => 'required|string|max:10',
            'codigoempleado' => 'required|string|max:20|alpha_num',
            'fechaalta' => 'required|date',

            // 🟡 Campos opcionales (nullable)
            'id' => 'nullable|numeric',
            'id_nomina_gape_cliente' => 'nullable|numeric',
            'idempleado' => 'nullable|numeric',
            'estado_empleado' => 'nullable|boolean',
            'iddepartamento' => 'nullable|numeric',
            'idpuesto' => 'nullable|numeric',
            'idturno' => 'nullable|numeric',

            'nombre' => 'nullable|string|max:60|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u',
            'apellidopaterno' => 'nullable|string|max:60|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u',
            'apellidomaterno' => 'nullable|string|max:60|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u',
            'nombrelargo' => 'nullable|string|max:120',

            'fechanacimiento' => 'nullable|date',
            'lugarnacimiento' => 'nullable|string|max:60|alpha',
            'estadocivil' => 'nullable|string|max:20',
            'sexo' => 'nullable|string|max:1',

            'curpi' => 'nullable|string|max:4',
            'curpf' => 'nullable|string|max:14',
            'curpCompleto' => 'nullable|string|size:18',

            'numerosegurosocial' => 'nullable|string|max:15',
            'umf' => 'nullable|numeric',
            'rfc' => 'nullable|string',

            'homoclave' => 'nullable|string|max:3',

            'cuentapagoelectronico' => 'nullable|alpha_num|max:20',
            'sucursalpagoelectronico' => 'nullable|string|max:50',
            'bancopagoelectronico' => 'nullable|string|max:50',
            'ClabeInterbancaria' => 'nullable|digits_between:18,30|numeric',

            'estadoempleado' => 'nullable|string|max:20',

            'sueldodiario' => 'nullable|numeric|min:0',
            'fechasueldodiario' => 'nullable|date',
            'sueldovariable' => 'nullable|numeric|min:0',
            'fechasueldovariable' => 'nullable|date',
            'sueldopromedio' => 'nullable|numeric|min:0',
            'fechasueldopromedio' => 'nullable|date',
            'sueldointegrado' => 'nullable|numeric|min:0',
            'fechasueldointegrado' => 'nullable|date',
            'sueldobaseliquidacion' => 'nullable|numeric|min:0.01',
            'fechareingreso' => 'nullable|date',
            'fechabaja' => 'nullable|date',

            'causabaja' => 'nullable|string|max:255',
            'sueldo_real' => 'nullable|numeric|min:0',
            'sueldo_imss_gape' => 'nullable|numeric|min:0',

            'tipocontrato' => 'nullable|string|max:10',
            'basecotizacionimss' => 'nullable|string|max:10',
            'tipoempleado' => 'nullable|string|max:10',
            'basepago' => 'nullable|string|max:10',
            'formapago' => 'nullable|string|max:10',
            'zonasalario' => 'nullable|string|max:10',

            'calculado' => 'nullable|boolean',
            'afectado' => 'nullable|boolean',
            'calculadoextraordinario' => 'nullable|boolean',
            'afectadoextraordinario' => 'nullable|boolean',
            'interfazcheqpaqw' => 'nullable|boolean',
            'modificacionneto' => 'nullable|boolean',
            'calculoptu' => 'nullable|boolean',
            'calculoaguinaldo' => 'nullable|boolean',
            'modificacionsalarioimss' => 'nullable|boolean',
            'altaimss' => 'nullable|boolean',
            'bajaimss' => 'nullable|boolean',
            'cambiocotizacionimss' => 'nullable|boolean',

            'expediente' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'codigopostal' => 'nullable|string|max:10',
            'direccion' => 'nullable|string|max:120',
            'poblacion' => 'nullable|string|max:120',
            'estado_emp' => 'nullable|string|max:120',

            'nombrepadre' => 'nullable|string|max:120',
            'nombremadre' => 'nullable|string|max:120',
            'numeroafore' => 'nullable|string|max:30',

            'ajustealneto' => 'nullable|numeric|min:0',
            'timestamp' => 'nullable|date',

            'cidregistropatronal' => 'nullable|numeric',
            'ccampoextranumerico1' => 'nullable|numeric',
            'ccampoextranumerico2' => 'nullable|numeric',
            'ccampoextranumerico3' => 'nullable|numeric',
            'ccampoextranumerico4' => 'nullable|numeric',
            'ccampoextranumerico5' => 'nullable|numeric',

            'cestadoempleadoperiodo' => 'nullable|string|max:50',
            'cfechasueldomixto' => 'nullable|date',
            'csueldomixto' => 'nullable|numeric|min:0',

            'NumeroFonacot' => 'nullable|string|max:30',
            'CorreoElectronico' => 'nullable|email|max:100',
            'TipoRegimen' => 'nullable|string|max:50',
            'EntidadFederativa' => 'nullable|string|max:50',

            'Subcontratacion' => 'nullable|boolean',
            'ExtranjeroSinCURP' => 'nullable|boolean',
            'TipoPrestacion' => 'nullable|numeric',
            'DiasVacTomadasAntesdeAlta' => 'nullable|numeric|min:0',
            'DiasPrimaVacTomadasAntesdeAlta' => 'nullable|numeric|min:0',
            'TipoSemanaReducida' => 'nullable|numeric|min:0',
            'Teletrabajador' => 'nullable|numeric|min:0',

            'Equipo' => 'nullable|string|max:255',
            'Insumo' => 'nullable|string|max:255',
            'DireccionTeletrabajo' => 'nullable|string|max:255',

            'carga_masiva' => 'nullable|boolean',
            'estado' => 'nullable|string|max:50',
            'fiscal' => 'nullable|boolean',
            'fecha_alta_gape' => 'nullable|date',
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
            'fechaalta.required' => 'La fecha de alta es obligatoria.',

            'curp.regex' => 'El CURP no tiene un formato válido.',
            'ClabeInterbancaria.digits_between' => 'La CLABE debe tener entre 18 y 30 dígitos.',
            'sueldobaseliquidacion.min' => 'Debe ser un número mayor a 0.',
        ];
    }
}
