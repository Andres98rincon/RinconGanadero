<?php

namespace App\Http\Requests;

use App\Models\Animal;
use App\Models\MilkProductionRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MilkProductionRecordRequest extends FormRequest
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
        $rules = [
            'record_date' => ['required', 'date', 'before_or_equal:today'],
            'morning_liters' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'evening_liters' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if (! $this->route('animal')) {
            $rules['animal_id'] = [
                'required',
                'integer',
                Rule::exists('animals', 'id')->where(fn ($query) => $query
                    ->where('species', 'bovine')
                    ->where('sex', 'female')
                    ->where('status', 'active')
                    ->whereNull('deleted_at')),
            ];
        }

        return $rules;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->has('record_date') && $this->filled('record_date')) {
                    $animal = $this->route('animal');
                    $productionRecord = $this->route('productionRecord');
                    $animalId = $animal instanceof Animal ? $animal->id : $this->input('animal_id');
                    $existingRecord = $animal instanceof Animal
                        ? $animal->productionRecords()
                            ->whereDate('record_date', $this->input('record_date'))
                            ->when($productionRecord instanceof MilkProductionRecord, fn ($query) => $query->whereKeyNot($productionRecord->getKey()))
                            ->exists()
                        : (is_numeric($animalId)
                            ? MilkProductionRecord::query()
                                ->where('animal_id', $animalId)
                                ->whereDate('record_date', $this->input('record_date'))
                                ->exists()
                            : false);

                    if ($existingRecord) {
                        $validator->errors()->add('record_date', 'Ya existe un registro de producción para esta fecha.');
                    }
                }

                if ($this->filled('morning_liters') || $this->filled('evening_liters')) {
                    return;
                }

                $validator->errors()->add('morning_liters', 'Registra al menos un ordeño para guardar la producción.');
            },
        ];
    }

    public function messages(): array
    {
        return [
            'animal_id.required' => 'Selecciona una vaca para registrar su producción.',
            'animal_id.exists' => 'Selecciona una vaca bovina activa.',
            'record_date.unique' => 'Ya existe un registro de producción para esta fecha.',
            'record_date.before_or_equal' => 'La fecha de producción no puede ser futura.',
        ];
    }
}
