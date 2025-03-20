<?php

namespace App\Http\Requests\core\sistema;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSistemaRequest extends FormRequest
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
        $sistemaId = $this->route('id'); // Asegúrate de que la ruta tenga el parámetro "id"

        return [
            'nombre' => 'required|unique:sistema,nombre,' . $sistemaId,
            'codigo' => 'required|unique:sistema,codigo,' . $sistemaId,
            'descripcion' => 'required',
            'estado' => 'required'
        ];
    }

    public function messages()
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'nombre.unique' => 'El nombre ya está en uso',
            'codigo.required' => 'El código es obligatorio',
            'codigo.unique' => 'El código ya está en uso',
            'descripcion.required' => 'La descripción es obligatoria',
            'estado.required' => 'El estado es obligatorio',
        ];
    }
}
