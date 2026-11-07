<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\AccessControl;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnimalValuationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'animal_id' => [
                'required',
                'integer',
                Rule::exists('animals', 'id')->where(fn ($query) => $query
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->when(! $this->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE), fn ($query) => $query
                        ->whereIn('farm_id', $this->user()->accessibleFarmIdsQuery()))
                    ->when($this->filled('farm_id'), fn ($query) => $query->where('farm_id', $this->input('farm_id')))),
            ],
            'farm_id' => ['nullable', 'integer', Rule::exists('farms', 'id')->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->when(! $this->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE), fn ($query) => $query
                    ->whereIn('id', $this->user()->accessibleFarmIdsQuery())))],
            'valuation_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'animal_id.required' => 'Selecciona un animal para valorar.',
            'animal_id.exists' => 'La valoración solo se puede registrar para animales activos.',
            'valuation_date.required' => 'Indica la fecha de valoración.',
            'valuation_date.before_or_equal' => 'La fecha de valoración no puede ser futura.',
            'amount.required' => 'Indica el valor estimado del animal.',
            'amount.min' => 'El valor no puede ser negativo.',
            'amount.max' => 'El valor supera el máximo permitido.',
        ];
    }
}
