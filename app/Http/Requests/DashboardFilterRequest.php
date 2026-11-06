<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
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
            'farm_id.exists' => 'La finca seleccionada no existe.',
        ];
    }
}
