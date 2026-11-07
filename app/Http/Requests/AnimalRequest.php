<?php

namespace App\Http\Requests;

use App\AccessControl;
use App\Models\Animal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AnimalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'ear_tag' => ['required', 'string', 'max:40', Rule::unique('animals', 'ear_tag')->ignore($this->route('animal'))],
            'name' => ['nullable', 'string', 'max:100'],
            'species' => ['required', Rule::in(array_keys(Animal::SPECIES))],
            'breed_id' => [
                'nullable',
                Rule::exists('breeds', 'id')->where(fn ($query) => $query->where('species', $this->input('species'))),
            ],
            'farm_id' => [
                Rule::requiredIf(fn (): bool => ! $this->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE)),
                'nullable',
                'integer',
                Rule::exists('farms', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->whereIn('id', $this->user()->accessibleFarmIdsQuery())),
            ],
            'sex' => ['required', Rule::in(['female', 'male', 'unknown'])],
            'identification_method' => ['required', Rule::in(['visual', 'rfid', 'tattoo', 'brand', 'other'])],
            'microchip' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('animals', 'microchip')->ignore($this->route('animal')),
            ],
            'registration_number' => ['nullable', 'string', 'max:64'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'birth_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'color' => ['nullable', 'string', 'max:80'],
            'markings' => ['nullable', 'string', 'max:5000'],
            'purpose' => ['nullable', Rule::in(['dairy', 'meat', 'breeding', 'dual', 'work', 'other'])],
            'status' => ['required', Rule::in(array_keys(Animal::STATUSES))],
            'reproductive_status' => ['nullable', Rule::in(['open', 'served', 'pregnant', 'lactating', 'dry', 'not_applicable'])],
            'location' => ['nullable', 'string', 'max:120'],
            'dam_id' => [
                'nullable',
                'integer',
                Rule::exists('animals', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->when(
                        ! $this->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE),
                        fn ($query) => $query->whereIn('farm_id', $this->user()->accessibleFarmIdsQuery()),
                    )),
                'different:sire_id',
            ],
            'sire_id' => [
                'nullable',
                'integer',
                Rule::exists('animals', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->when(
                        ! $this->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE),
                        fn ($query) => $query->whereIn('farm_id', $this->user()->accessibleFarmIdsQuery()),
                    )),
                'different:dam_id',
            ],
            'acquisition_type' => ['required', Rule::in(['born', 'purchased', 'donated', 'transferred', 'unknown'])],
            'acquisition_date' => ['nullable', 'date', 'before_or_equal:today'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'current_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'last_weighed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'health_notes' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:20480'],
            'remove_photo' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['species', 'sex'])) {
                return;
            }

            $currentAnimal = $this->route('animal');

            if (
                $currentAnimal instanceof Animal
                && $currentAnimal->movements()->exists()
                && (
                    ($this->integer('farm_id') ?: null) !== $currentAnimal->farm_id
                    || ($this->input('location') ?: null) !== $currentAnimal->location
                )
            ) {
                $validator->errors()->add('farm_id', 'La ubicación de un animal con historial se cambia desde Compras, ventas y traslados.');
            }

            if (
                $currentAnimal instanceof Animal
                && $currentAnimal->status !== $this->input('status')
                && in_array('sold', [$currentAnimal->status, $this->input('status')], true)
            ) {
                $validator->errors()->add('status', 'El estado de venta se actualiza al registrar la operación desde Compras, ventas y traslados.');
            }

            if (
                $currentAnimal instanceof Animal
                && $currentAnimal->movements()->where('movement_type', 'purchase')->exists()
            ) {
                foreach ([
                    'acquisition_type' => 'El tipo de adquisición se conserva en el historial de compra.',
                    'acquisition_date' => 'La fecha de compra se conserva en el historial de movimientos.',
                    'acquisition_cost' => 'El precio de compra se conserva en el historial financiero.',
                    'supplier_name' => 'El proveedor se conserva en el historial de compra.',
                ] as $field => $message) {
                    $currentValue = $currentAnimal->getAttribute($field);
                    $submittedValue = $this->input($field);

                    if ($field === 'acquisition_date') {
                        $currentValue = $currentAnimal->acquisition_date?->toDateString();
                        $submittedValue = $submittedValue ?: null;
                    } elseif ($field === 'acquisition_cost') {
                        $currentValue = $currentValue === null ? null : number_format((float) $currentValue, 2, '.', '');
                        $submittedValue = $submittedValue === null || $submittedValue === ''
                            ? null
                            : (is_numeric($submittedValue) ? number_format((float) $submittedValue, 2, '.', '') : $submittedValue);
                    } else {
                        $currentValue = $currentValue ?: null;
                        $submittedValue = $submittedValue ?: null;
                    }

                    if ($currentValue !== $submittedValue) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }

            foreach ([
                'dam_id' => ['female', 'La madre debe ser hembra.'],
                'sire_id' => ['male', 'El padre debe ser macho.'],
            ] as $field => [$expectedSex, $message]) {
                if ($validator->errors()->has($field) || ! $this->filled($field)) {
                    continue;
                }

                $parent = Animal::query()->find($this->integer($field));

                if (! $parent) {
                    continue;
                }

                if ($currentAnimal instanceof Animal && $currentAnimal->is($parent)) {
                    $validator->errors()->add($field, 'Un animal no puede ser su propio progenitor.');
                } elseif ($parent->sex !== $expectedSex) {
                    $validator->errors()->add($field, $message);
                } elseif ($parent->species !== $this->input('species')) {
                    $validator->errors()->add($field, 'El progenitor debe ser de la misma especie.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'ear_tag.required' => 'La identificación del animal es obligatoria.',
            'ear_tag.unique' => 'Esta identificación ya está registrada.',
            'species.required' => 'Selecciona la especie del animal antes de elegir su raza.',
            'species.in' => 'La especie seleccionada no es válida.',
            'breed_id.exists' => 'La raza seleccionada no corresponde a la especie del animal.',
            'photo.image' => 'El archivo seleccionado debe ser una imagen válida.',
            'photo.mimes' => 'La foto debe estar en formato JPG, PNG o WEBP.',
            'photo.extensions' => 'La extensión de la foto debe ser JPG, PNG o WEBP.',
            'photo.max' => 'La foto no puede pesar más de 5 MB.',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            'acquisition_date.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
            'last_weighed_at.before_or_equal' => 'La fecha del ultimo pesaje no puede ser futura.',
        ];
    }
}
