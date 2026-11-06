<?php

namespace App\Http\Requests;

use App\AccessControl;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->input('from', now()->startOfMonth()->toDateString()),
            'to' => $this->input('to', today()->toDateString()),
        ]);
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date', 'before_or_equal:today'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:today'],
            'farm_id' => ['nullable', 'integer', Rule::exists('farms', 'id')->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->whereIn('id', $this->user()->accessibleFarmIdsQuery()))],
            'animal_id' => ['nullable', 'integer', Rule::exists('animals', 'id')->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->when(
                    ! $this->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE),
                    fn ($query) => $query->whereIn('farm_id', $this->user()->accessibleFarmIdsQuery()),
                ))],
            'type' => ['nullable', Rule::in(array_keys(FinancialTransaction::TYPES))],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.required' => 'Indica la fecha inicial del período.',
            'from.date' => 'La fecha inicial no es válida.',
            'from.before_or_equal' => 'La fecha inicial no puede ser futura.',
            'to.required' => 'Indica la fecha final del período.',
            'to.date' => 'La fecha final no es válida.',
            'to.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'to.before_or_equal' => 'La fecha final no puede ser futura.',
            'farm_id.exists' => 'La finca seleccionada no existe.',
            'animal_id.exists' => 'El animal seleccionado no existe.',
            'type.in' => 'Selecciona ingreso o gasto.',
        ];
    }
}
