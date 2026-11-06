<?php

namespace App\Http\Requests;

use App\Models\Paddock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaddockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        $paddock = $this->route('paddock');

        return [
            'farm_id' => [
                'required',
                'integer',
                Rule::exists('farms', 'id')->where(fn ($query) => $query
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->whereIn('id', $this->user()->accessibleFarmIdsQuery())),
            ],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('paddocks', 'code')
                    ->where(fn ($query) => $query->where('farm_id', $this->integer('farm_id')))
                    ->ignore($paddock),
            ],
            'name' => ['required', 'string', 'max:120'],
            'area_hectares' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'status' => ['required', Rule::in(array_keys(Paddock::STATUSES))],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'farm_id.required' => 'Selecciona la finca del potrero.',
            'farm_id.exists' => 'La finca no existe o no está activa.',
            'code.required' => 'Indica el código del potrero.',
            'code.unique' => 'Ese código ya está registrado en la finca seleccionada.',
            'name.required' => 'Indica el nombre del potrero.',
            'area_hectares.min' => 'El área no puede ser negativa.',
            'capacity.min' => 'La capacidad debe ser mayor que cero.',
            'status.in' => 'Selecciona un estado válido.',
        ];
    }
}
