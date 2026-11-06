<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
{
    public const REPORTS = [
        'inventory' => 'Inventario actual',
        'reproduction' => 'Reproducción',
        'milk' => 'Producción de leche',
        'weights' => 'Pesajes y crecimiento',
        'movements' => 'Compras, ventas y traslados',
        'finance' => 'Finanzas y costos',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'report' => $this->input('report', 'inventory'),
            'from' => $this->input('from', now()->startOfMonth()->toDateString()),
            'to' => $this->input('to', today()->toDateString()),
        ]);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'report' => ['required', Rule::in(array_keys(self::REPORTS))],
            'from' => ['required', 'date', 'before_or_equal:today'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:today'],
            'farm_id' => ['nullable', 'integer', Rule::exists('farms', 'id')->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->whereIn('id', $this->user()->accessibleFarmIdsQuery()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'report.in' => 'Selecciona un tipo de reporte válido.',
            'from.required' => 'Indica la fecha inicial del período.',
            'from.date' => 'La fecha inicial no es válida.',
            'from.before_or_equal' => 'La fecha inicial no puede ser futura.',
            'to.required' => 'Indica la fecha final del período.',
            'to.date' => 'La fecha final no es válida.',
            'to.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'to.before_or_equal' => 'La fecha final no puede ser futura.',
            'farm_id.exists' => 'La finca seleccionada no existe.',
        ];
    }
}
