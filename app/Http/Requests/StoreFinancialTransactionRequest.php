<?php

namespace App\Http\Requests;

use App\AccessControl;
use App\Models\Animal;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFinancialTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(FinancialTransaction::TYPES))],
            'category' => [
                'required',
                Rule::in(array_keys(FinancialTransaction::CATEGORIES[$this->input('type')] ?? [])),
                Rule::notIn(FinancialTransaction::MOVEMENT_ONLY_CATEGORIES),
            ],
            'title' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'farm_id' => [
                'nullable',
                'integer',
                Rule::exists('farms', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->whereIn('id', $this->user()->accessibleFarmIdsQuery())),
            ],
            'animal_id' => ['nullable', 'integer', Rule::exists('animals', 'id')->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->when(
                    ! $this->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE),
                    fn ($query) => $query->whereIn('farm_id', $this->user()->accessibleFarmIdsQuery()),
                ))],
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
                if ($validator->errors()->hasAny(['farm_id', 'animal_id']) || ! $this->filled('animal_id') || ! $this->filled('farm_id')) {
                    if (
                        ! $this->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE)
                        && ! $this->filled('animal_id')
                        && ! $this->filled('farm_id')
                    ) {
                        $validator->errors()->add('farm_id', 'Selecciona la finca o el animal relacionado con este movimiento.');
                    }

                    return;
                }

                $animal = Animal::query()->find($this->integer('animal_id'));

                if ($animal?->farm_id && $animal->farm_id !== $this->integer('farm_id')) {
                    $validator->errors()->add('farm_id', 'La finca seleccionada no corresponde al animal.');
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
            'type.required' => 'Selecciona si es un ingreso o un gasto.',
            'category.required' => 'Selecciona una categoría.',
            'category.in' => 'La categoría no corresponde al tipo de movimiento.',
            'category.not_in' => 'Las compras y ventas de animales se registran desde el módulo de movimientos.',
            'title.required' => 'Describe el movimiento.',
            'amount.required' => 'Indica el monto del movimiento.',
            'amount.gt' => 'El monto debe ser mayor que cero.',
            'amount.max' => 'El monto supera el máximo permitido.',
            'transaction_date.required' => 'Indica la fecha del movimiento.',
            'transaction_date.before_or_equal' => 'La fecha del movimiento no puede ser futura.',
            'farm_id.exists' => 'La finca seleccionada no existe.',
            'animal_id.exists' => 'El animal seleccionado no existe.',
            'payment_method.in' => 'Selecciona un medio de pago válido.',
        ];
    }
}
