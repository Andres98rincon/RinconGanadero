<?php

namespace App\Http\Requests;

use App\Models\Animal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WeightRecordRequest extends FormRequest
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
        return [
            'animal_id' => [
                'required',
                'integer',
                Rule::exists('animals', 'id')->where(fn ($query) => $query
                    ->where('status', 'active')
                    ->whereNull('deleted_at')),
            ],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'weight_kg' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['animal_id', 'event_date']) || ! $this->filled('event_date')) {
                    return;
                }

                $animal = Animal::query()->find($this->integer('animal_id'));

                if ($animal?->birth_date && $animal->birth_date->greaterThan($this->date('event_date'))) {
                    $validator->errors()->add('event_date', 'La fecha del pesaje no puede ser anterior al nacimiento.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'animal_id.required' => 'Selecciona un animal para registrar su peso.',
            'animal_id.exists' => 'Selecciona un animal activo del inventario.',
            'event_date.required' => 'Indica la fecha del pesaje.',
            'event_date.before_or_equal' => 'La fecha del pesaje no puede ser futura.',
            'weight_kg.required' => 'Indica el peso medido.',
            'weight_kg.min' => 'El peso debe ser mayor que cero.',
            'weight_kg.max' => 'El peso supera el máximo permitido.',
        ];
    }
}
