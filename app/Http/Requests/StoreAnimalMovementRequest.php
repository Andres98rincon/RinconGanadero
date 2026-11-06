<?php

namespace App\Http\Requests;

use App\Models\Animal;
use App\Models\AnimalMovement;
use App\Models\FinancialTransaction;
use App\Models\Paddock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAnimalMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('movement_type');
        $purchase = $type === 'purchase';
        $sale = $type === 'sale';
        $farmTransfer = $type === 'farm_transfer';
        $paddockTransfer = $type === 'paddock_transfer';
        $requiresAnimal = ! $purchase;

        return [
            'movement_type' => ['required', Rule::in(array_keys(AnimalMovement::TYPES))],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'animal_id' => [
                Rule::requiredIf($requiresAnimal),
                Rule::prohibitedIf($purchase),
                'integer',
                Rule::exists('animals', 'id')->where(fn ($query) => $query
                    ->where('status', 'active')
                    ->whereNull('deleted_at')),
            ],
            'ear_tag' => [
                Rule::requiredIf($purchase),
                Rule::prohibitedIf(! $purchase),
                'string',
                'max:40',
                Rule::unique('animals', 'ear_tag')->whereNull('deleted_at'),
            ],
            'name' => [Rule::prohibitedIf(! $purchase), 'nullable', 'string', 'max:100'],
            'species' => [
                Rule::requiredIf($purchase),
                Rule::prohibitedIf(! $purchase),
                Rule::in(array_keys(Animal::SPECIES)),
            ],
            'breed_id' => [
                Rule::prohibitedIf(! $purchase),
                'nullable',
                'integer',
                Rule::exists('breeds', 'id')->where(fn ($query) => $query->where('species', $this->input('species'))),
            ],
            'sex' => [
                Rule::requiredIf($purchase),
                Rule::prohibitedIf(! $purchase),
                Rule::in(['female', 'male', 'unknown']),
            ],
            'birth_date' => [Rule::prohibitedIf(! $purchase), 'nullable', 'date', 'before_or_equal:today'],
            'to_farm_id' => [
                Rule::requiredIf($purchase || $farmTransfer),
                Rule::prohibitedIf($sale || $paddockTransfer),
                'integer',
                Rule::exists('farms', 'id')->where(fn ($query) => $query
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->whereIn('id', $this->user()->accessibleFarmIdsQuery())),
            ],
            'to_paddock_id' => [
                Rule::requiredIf($paddockTransfer),
                'nullable',
                'integer',
                Rule::exists('paddocks', 'id')->where(fn ($query) => $query
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->whereIn('farm_id', $this->user()->accessibleFarmIdsQuery())),
            ],
            'amount' => [
                Rule::requiredIf($purchase || $sale),
                Rule::prohibitedIf(! $purchase && ! $sale),
                'numeric',
                'gt:0',
                'max:9999999999.99',
            ],
            'exit_reason' => [
                Rule::requiredIf($sale),
                Rule::prohibitedIf(! $sale),
                Rule::in(array_keys(AnimalMovement::EXIT_REASONS)),
            ],
            'counterparty_name' => ['nullable', 'string', 'max:150'],
            'payment_method' => ['nullable', Rule::in(array_keys(FinancialTransaction::PAYMENT_METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
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
                if ($validator->errors()->hasAny(['movement_type', 'animal_id', 'to_farm_id', 'to_paddock_id'])) {
                    return;
                }

                $type = $this->input('movement_type');
                $animal = $this->filled('animal_id')
                    ? Animal::query()->with('paddock')->find($this->integer('animal_id'))
                    : null;
                $paddock = $this->filled('to_paddock_id')
                    ? Paddock::query()->find($this->integer('to_paddock_id'))
                    : null;

                if ($paddock && in_array($type, ['purchase', 'farm_transfer'], true) && $paddock->farm_id !== $this->integer('to_farm_id')) {
                    $validator->errors()->add('to_paddock_id', 'El potrero seleccionado no pertenece a la finca de destino.');
                }

                if ($type === 'farm_transfer' && $animal?->farm_id === $this->integer('to_farm_id')) {
                    $validator->errors()->add('to_farm_id', 'Selecciona una finca diferente a la ubicación actual.');
                }

                if ($type === 'paddock_transfer') {
                    if (! $animal?->farm_id || $paddock?->farm_id !== $animal->farm_id) {
                        $validator->errors()->add('to_paddock_id', 'El potrero de destino debe pertenecer a la finca actual del animal.');
                    } elseif ($animal->paddock_id === $paddock->id) {
                        $validator->errors()->add('to_paddock_id', 'Selecciona un potrero diferente al actual.');
                    }
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
            'movement_type.required' => 'Selecciona el tipo de movimiento.',
            'movement_type.in' => 'Selecciona un tipo de movimiento válido.',
            'movement_date.required' => 'Indica la fecha del movimiento.',
            'movement_date.before_or_equal' => 'La fecha no puede ser futura.',
            'animal_id.required' => 'Selecciona el animal que se va a mover.',
            'animal_id.exists' => 'El animal no existe o no está activo.',
            'ear_tag.required' => 'Indica la identificación del animal comprado.',
            'ear_tag.unique' => 'Esta identificación ya está registrada.',
            'species.required' => 'Selecciona la especie del animal.',
            'sex.required' => 'Selecciona el sexo del animal.',
            'to_farm_id.required' => 'Selecciona la finca de destino.',
            'to_farm_id.exists' => 'La finca de destino no existe o no está activa.',
            'to_paddock_id.required' => 'Selecciona el potrero de destino.',
            'to_paddock_id.exists' => 'El potrero de destino no existe o no está activo.',
            'amount.required' => 'Indica el precio de la operación.',
            'amount.gt' => 'El precio debe ser mayor que cero.',
            'amount.max' => 'El precio supera el máximo permitido.',
            'exit_reason.required' => 'Selecciona el motivo de salida del animal.',
            'exit_reason.in' => 'Selecciona un motivo de salida válido.',
        ];
    }
}
