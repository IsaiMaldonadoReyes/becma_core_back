<?php

namespace App\Http\Requests\nomina\gape\cliente;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
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
            'nombre' => 'required|unique:nomina_gape_cliente,nombre',
            'codigo' => 'required|unique:nomina_gape_cliente,codigo',
            'telefono' => 'required',
            'estado' => 'required|boolean',
        ];
    }
    public function messages()
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'nombre.unique' => 'El nombre ya está en uso',
            'codigo.required' => 'El código es obligatorio',
            'codigo.unique' => 'El código ya está en uso',
            'telefono.required' => 'El teléfono es obligatorio',
            'estado.required' => 'El estado es obligatorio',
            'estado.boolean' => 'El estado debe ser v/f',
        ];
    }
}
