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
            'province' => ['nullable', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && $value !== $this->route('farm')?->province
                    && ! \App\Models\Department::query()->where('name', $value)->exists()) {
                    $fail('Selecciona un departamento de la lista.');
                }
            }],
            'municipality' => ['nullable', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail): void {
                if (blank($value)) {
                    return;
                }

                $farm = $this->route('farm');
                $isUnchangedLegacyValue = $farm && $value === $farm->municipality && $this->input('province') === $farm->province;
                $belongsToDepartment = \App\Models\Municipality::query()
                    ->where('name', $value)
                    ->whereHas('department', fn ($query) => $query->where('name', $this->input('province')))
                    ->exists();

                if (! $isUnchangedLegacyValue && ! $belongsToDepartment) {
                    $fail(blank($this->input('province'))
                        ? 'Selecciona primero el departamento.'
                        : 'El municipio no pertenece al departamento seleccionado.');
                }
            }],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
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
