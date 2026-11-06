<?php

namespace App\Http\Requests;

use App\Models\ReproductiveEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReproductiveEventRequest extends FormRequest
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
            'animal_id' => [
                'required',
                'integer',
                Rule::exists('animals', 'id')->where(fn ($query) => $query
                    ->where('species', 'bovine')
                    ->where('sex', 'female')
                    ->where('status', 'active')
                    ->whereNull('deleted_at')),
            ],
            'event_type' => ['required', Rule::in(array_keys(ReproductiveEvent::TYPES))],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'sire_animal_id' => [
                'exclude_unless:event_type,natural_mating,artificial_insemination,calving',
                'nullable',
                'integer',
                Rule::exists('animals', 'id')->where(fn ($query) => $query
                    ->where('species', 'bovine')
                    ->where('sex', 'male')
                    ->where('status', 'active')
                    ->whereNull('deleted_at')),
            ],
            'sire_name' => [
                'exclude_unless:event_type,natural_mating,artificial_insemination,calving',
                'nullable',
                'string',
                'max:150',
            ],
            'semen_code' => [
                'exclude_unless:event_type,artificial_insemination',
                'nullable',
                'string',
                'max:100',
            ],
            'technician' => [
                'exclude_unless:event_type,artificial_insemination',
                'nullable',
                'string',
                'max:150',
            ],
            'diagnosis_result' => [
                'exclude_unless:event_type,pregnancy_diagnosis',
                'required',
                Rule::in(['pregnant', 'not_pregnant']),
            ],
            'expected_calving_date' => [
                'exclude_unless:event_type,pregnancy_diagnosis',
                'nullable',
                'date',
                'after:event_date',
                'required_if:diagnosis_result,pregnant',
            ],
            'gestation_weeks' => [
                'exclude_unless:event_type,abortion',
                'nullable',
                'integer',
                'between:1,45',
            ],
            'calving_difficulty' => [
                'exclude_unless:event_type,calving',
                'required',
                Rule::in(['normal', 'assisted', 'cesarean']),
            ],
            'calves' => [
                'exclude_unless:event_type,calving',
                'required_if:event_type,calving',
                'array',
                'min:1',
                'max:10',
            ],
            'calves.*.ear_tag' => [
                'exclude_unless:event_type,calving',
                'required',
                'string',
                'max:40',
                'distinct',
                Rule::unique('animals', 'ear_tag'),
            ],
            'calves.*.name' => ['exclude_unless:event_type,calving', 'nullable', 'string', 'max:100'],
            'calves.*.sex' => ['exclude_unless:event_type,calving', 'required', Rule::in(['female', 'male'])],
            'calves.*.birth_weight_kg' => ['exclude_unless:event_type,calving', 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! in_array($this->input('event_type'), ['natural_mating', 'artificial_insemination'], true)) {
                    return;
                }

                if ($this->filled('sire_animal_id') || $this->filled('sire_name')) {
                    return;
                }

                $validator->errors()->add('sire_animal_id', 'Registra el toro o el nombre del semental utilizado.');
            },
        ];
    }

    public function messages(): array
    {
        return [
            'animal_id.exists' => 'Selecciona una vaca bovina activa.',
            'sire_animal_id.exists' => 'El semental debe ser un bovino macho activo.',
            'event_date.before_or_equal' => 'La fecha del evento no puede ser futura.',
            'expected_calving_date.required_if' => 'Indica la fecha probable de parto para confirmar la preñez.',
            'expected_calving_date.after' => 'La fecha probable de parto debe ser posterior al evento.',
            'calves.*.ear_tag.unique' => 'La identificación del ternero ya está registrada.',
            'calves.*.ear_tag.distinct' => 'Cada ternero debe tener una identificación diferente.',
        ];
    }
}
