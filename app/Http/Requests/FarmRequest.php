<?php

namespace App\Http\Requests;

use App\Models\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FarmRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('farms', 'code')->ignore($this->route('farm')),
            ],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'area_hectares' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'manager_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'status' => ['required', Rule::in(array_keys(Farm::STATUSES))],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código de la finca es obligatorio.',
            'code.unique' => 'Este código ya está registrado.',
            'name.required' => 'El nombre de la finca es obligatorio.',
            'area_hectares.min' => 'La extensión no puede ser negativa.',
            'email.email' => 'Ingresa un correo electrónico válido.',
        ];
    }
}
