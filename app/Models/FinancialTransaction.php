<?php

namespace App\Models;

use Database\Factories\FinancialTransactionFactory;
use App\Models\Concerns\ScopesToAccessibleFarms;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FinancialTransaction extends Model
{
    public const TYPES = [
        'income' => 'Ingreso',
        'expense' => 'Gasto',
    ];

    public const CATEGORIES = [
        'income' => [
            'milk_sales' => 'Venta de leche',
            'animal_sales' => 'Venta de animales',
            'livestock_products' => 'Otros productos ganaderos',
            'subsidies' => 'Subsidios y apoyos',
            'other' => 'Otros ingresos',
        ],
        'expense' => [
            'feed' => 'Alimentación',
            'veterinary' => 'Veterinaria y medicamentos',
            'labor' => 'Mano de obra',
            'reproduction' => 'Reproducción',
            'transport' => 'Transporte',
            'maintenance' => 'Mantenimiento',
            'utilities' => 'Servicios públicos',
            'animal_purchase' => 'Compra de animales',
            'other' => 'Otros gastos',
        ],
    ];

    public const PAYMENT_METHODS = [
        'cash' => 'Efectivo',
        'bank_transfer' => 'Transferencia',
        'card' => 'Tarjeta',
        'check' => 'Cheque',
        'other' => 'Otro',
    ];

    public const MOVEMENT_ONLY_CATEGORIES = [
        'animal_purchase',
        'animal_sales',
    ];

    /** @use HasFactory<FinancialTransactionFactory> */
    use HasFactory, LogsActivity, ScopesToAccessibleFarms;

    protected $fillable = [
        'type',
        'category',
        'title',
        'amount',
        'transaction_date',
        'farm_id',
        'animal_id',
        'payment_method',
        'reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('finance');
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }
}
