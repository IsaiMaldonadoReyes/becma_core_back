<?php

namespace App\Http\Requests\nomina\gape\Empleado;

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
            'ClabeInterbancaria' => 'nullable|digits_between:10,30|numeric',

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
            // 🟢 Campos requeridos
            'id_nomina_gape_empresa.required' => 'Selecciona una empresa.',
            'idtipoperiodo.required' => 'Selecciona un tipo de periodo.',
            'tipocontrato.required' => 'Selecciona un tipo de contrato.',
            'basecotizacionimss.required' => 'Selecciona una base de cotización.',
            'tipoempleado.required' => 'Selecciona el tipo de empleado.',
            'codigoempleado.required' => 'El código de empleado es obligatorio.',
            'codigoempleado.alpha_num' => 'El código de empleado solo puede contener letras y números.',
            'codigoempleado.max' => 'El código de empleado no debe superar los 20 caracteres.',
            'fechaalta.required' => 'La fecha de alta es obligatoria.',

            // 🟡 Identificadores
            'id.numeric' => 'El identificador debe ser numérico.',
            'id_nomina_gape_cliente.numeric' => 'El identificador del cliente debe ser numérico.',
            'idempleado.numeric' => 'El número de empleado debe ser numérico.',
            'estado_empleado.boolean' => 'El estado del empleado debe ser verdadero o falso.',
            'iddepartamento.numeric' => 'El departamento seleccionado no es válido.',
            'idpuesto.numeric' => 'El puesto seleccionado no es válido.',
            'idturno.numeric' => 'El turno seleccionado no es válido.',

            // 👤 Datos personales
            'nombre.string' => 'El nombre debe ser texto.',
            'nombre.max' => 'El nombre no debe superar los 60 caracteres.',
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'apellidopaterno.string' => 'El apellido paterno debe ser texto.',
            'apellidopaterno.max' => 'El apellido paterno no debe superar los 60 caracteres.',
            'apellidopaterno.regex' => 'El apellido paterno solo puede contener letras y espacios.',
            'apellidomaterno.string' => 'El apellido materno debe ser texto.',
            'apellidomaterno.max' => 'El apellido materno no debe superar los 60 caracteres.',
            'apellidomaterno.regex' => 'El apellido materno solo puede contener letras y espacios.',
            'nombrelargo.max' => 'El nombre completo no debe superar los 120 caracteres.',

            'fechanacimiento.date' => 'La fecha de nacimiento no tiene un formato válido.',
            'lugarnacimiento.alpha' => 'El lugar de nacimiento solo puede contener letras.',
            'lugarnacimiento.max' => 'El lugar de nacimiento no debe superar los 60 caracteres.',
            'estadocivil.max' => 'El estado civil no debe superar los 20 caracteres.',
            'sexo.max' => 'El campo sexo debe tener solo un carácter.',

            // 🧾 CURP, RFC, NSS
            'curpi.max' => 'El prefijo del CURP no debe superar los 4 caracteres.',
            'curpf.max' => 'El sufijo del CURP no debe superar los 14 caracteres.',
            'curpCompleto.size' => 'El CURP completo debe tener exactamente 18 caracteres.',
            'numerosegurosocial.max' => 'El número de seguro social no debe superar los 15 caracteres.',
            'umf.numeric' => 'El número de UMF debe ser numérico.',
            'rfc.string' => 'El RFC debe ser texto.',
            'homoclave.max' => 'La homoclave no debe superar los 3 caracteres.',

            // 🏦 Datos bancarios
            'cuentapagoelectronico.alpha_num' => 'La cuenta de pago electrónico solo puede contener letras y números.',
            'cuentapagoelectronico.max' => 'La cuenta de pago no debe superar los 20 caracteres.',
            'sucursalpagoelectronico.max' => 'La sucursal no debe superar los 50 caracteres.',
            'bancopagoelectronico.max' => 'El nombre del banco no debe superar los 50 caracteres.',
            'ClabeInterbancaria.numeric' => 'La CLABE interbancaria solo puede contener números.',
            'ClabeInterbancaria.digits_between' => 'La CLABE debe tener entre 10 y 30 dígitos.',

            // 📅 Fechas laborales
            'fechasueldodiario.date' => 'La fecha de sueldo diario no tiene un formato válido.',
            'fechasueldovariable.date' => 'La fecha de sueldo variable no tiene un formato válido.',
            'fechasueldopromedio.date' => 'La fecha de sueldo promedio no tiene un formato válido.',
            'fechasueldointegrado.date' => 'La fecha de sueldo integrado no tiene un formato válido.',
            'fechareingreso.date' => 'La fecha de reingreso no tiene un formato válido.',
            'fechabaja.date' => 'La fecha de baja no tiene un formato válido.',
            'fecha_alta_gape.date' => 'La fecha de alta GAPE no tiene un formato válido.',

            // 💰 Sueldos
            'sueldodiario.numeric' => 'El sueldo diario debe ser numérico.',
            'sueldodiario.min' => 'El sueldo diario no puede ser negativo.',
            'sueldovariable.numeric' => 'El sueldo variable debe ser numérico.',
            'sueldovariable.min' => 'El sueldo variable no puede ser negativo.',
            'sueldopromedio.numeric' => 'El sueldo promedio debe ser numérico.',
            'sueldopromedio.min' => 'El sueldo promedio no puede ser negativo.',
            'sueldointegrado.numeric' => 'El sueldo integrado debe ser numérico.',
            'sueldointegrado.min' => 'El sueldo integrado no puede ser negativo.',
            'sueldobaseliquidacion.numeric' => 'El sueldo base de liquidación debe ser numérico.',
            'sueldobaseliquidacion.min' => 'El sueldo base de liquidación debe ser mayor a 0.',
            'sueldo_real.numeric' => 'El sueldo real debe ser numérico.',
            'sueldo_real.min' => 'El sueldo real no puede ser negativo.',
            'sueldo_imss_gape.numeric' => 'El sueldo IMSS GAPE debe ser numérico.',
            'sueldo_imss_gape.min' => 'El sueldo IMSS GAPE no puede ser negativo.',

            // 📄 Contrato y clasificación
            'basepago.max' => 'La base de pago no debe superar los 10 caracteres.',
            'formapago.max' => 'La forma de pago no debe superar los 10 caracteres.',
            'zonasalario.max' => 'La zona salarial no debe superar los 10 caracteres.',

            // ⚙️ Banderas booleanas
            'calculado.boolean' => 'El campo calculado debe ser verdadero o falso.',
            'afectado.boolean' => 'El campo afectado debe ser verdadero o falso.',
            'calculadoextraordinario.boolean' => 'El campo calculado extraordinario debe ser verdadero o falso.',
            'afectadoextraordinario.boolean' => 'El campo afectado extraordinario debe ser verdadero o falso.',
            'interfazcheqpaqw.boolean' => 'El campo interfaz debe ser verdadero o falso.',
            'modificacionneto.boolean' => 'El campo modificación neto debe ser verdadero o falso.',
            'calculoptu.boolean' => 'El campo cálculo PTU debe ser verdadero o falso.',
            'calculoaguinaldo.boolean' => 'El campo cálculo aguinaldo debe ser verdadero o falso.',
            'modificacionsalarioimss.boolean' => 'El campo modificación salario IMSS debe ser verdadero o falso.',
            'altaimss.boolean' => 'El campo alta IMSS debe ser verdadero o falso.',
            'bajaimss.boolean' => 'El campo baja IMSS debe ser verdadero o falso.',
            'cambiocotizacionimss.boolean' => 'El campo cambio cotización IMSS debe ser verdadero o falso.',

            // 🏠 Dirección
            'expediente.max' => 'El número de expediente no debe superar los 255 caracteres.',
            'telefono.max' => 'El teléfono no debe superar los 20 caracteres.',
            'codigopostal.max' => 'El código postal no debe superar los 10 caracteres.',
            'direccion.max' => 'La dirección no debe superar los 120 caracteres.',
            'poblacion.max' => 'La población no debe superar los 120 caracteres.',
            'estado_emp.max' => 'El estado no debe superar los 120 caracteres.',

            // 👪 Familia
            'nombrepadre.max' => 'El nombre del padre no debe superar los 120 caracteres.',
            'nombremadre.max' => 'El nombre de la madre no debe superar los 120 caracteres.',
            'numeroafore.max' => 'El número de AFORE no debe superar los 30 caracteres.',

            // ⚖️ Otros
            'ajustealneto.numeric' => 'El ajuste al neto debe ser numérico.',
            'ajustealneto.min' => 'El ajuste al neto no puede ser negativo.',
            'timestamp.date' => 'La fecha de registro no tiene un formato válido.',
            'cidregistropatronal.numeric' => 'El registro patronal debe ser numérico.',

            // Campos extra numéricos
            'ccampoextranumerico1.numeric' => 'El campo extra numérico 1 debe ser numérico.',
            'ccampoextranumerico2.numeric' => 'El campo extra numérico 2 debe ser numérico.',
            'ccampoextranumerico3.numeric' => 'El campo extra numérico 3 debe ser numérico.',
            'ccampoextranumerico4.numeric' => 'El campo extra numérico 4 debe ser numérico.',
            'ccampoextranumerico5.numeric' => 'El campo extra numérico 5 debe ser numérico.',

            'cestadoempleadoperiodo.max' => 'El estado del empleado por periodo no debe superar los 50 caracteres.',
            'cfechasueldomixto.date' => 'La fecha de sueldo mixto no tiene un formato válido.',
            'csueldomixto.numeric' => 'El sueldo mixto debe ser numérico.',
            'csueldomixto.min' => 'El sueldo mixto no puede ser negativo.',

            'NumeroFonacot.max' => 'El número de FONACOT no debe superar los 30 caracteres.',
            'CorreoElectronico.email' => 'El correo electrónico no tiene un formato válido.',
            'CorreoElectronico.max' => 'El correo electrónico no debe superar los 100 caracteres.',
            'TipoRegimen.max' => 'El tipo de régimen no debe superar los 50 caracteres.',
            'EntidadFederativa.max' => 'La entidad federativa no debe superar los 50 caracteres.',

            'Subcontratacion.boolean' => 'El campo subcontratación debe ser verdadero o falso.',
            'ExtranjeroSinCURP.boolean' => 'El campo extranjero sin CURP debe ser verdadero o falso.',
            'TipoPrestacion.numeric' => 'El tipo de prestación debe ser numérico.',
            'DiasVacTomadasAntesdeAlta.numeric' => 'Los días de vacaciones deben ser numéricos.',
            'DiasVacTomadasAntesdeAlta.min' => 'Los días de vacaciones no pueden ser negativos.',
            'DiasPrimaVacTomadasAntesdeAlta.numeric' => 'Los días de prima vacacional deben ser numéricos.',
            'DiasPrimaVacTomadasAntesdeAlta.min' => 'Los días de prima vacacional no pueden ser negativos.',
            'TipoSemanaReducida.numeric' => 'El tipo de semana reducida debe ser numérico.',
            'Teletrabajador.numeric' => 'El campo teletrabajador debe ser numérico.',

            'Equipo.max' => 'El nombre del equipo no debe superar los 255 caracteres.',
            'Insumo.max' => 'El nombre del insumo no debe superar los 255 caracteres.',
            'DireccionTeletrabajo.max' => 'La dirección de teletrabajo no debe superar los 255 caracteres.',

            'carga_masiva.boolean' => 'El campo carga masiva debe ser verdadero o falso.',
            'estado.max' => 'El estado no debe superar los 50 caracteres.',
            'fiscal.boolean' => 'El campo fiscal debe ser verdadero o falso.',
        ];
    }
}
