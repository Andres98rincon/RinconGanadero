<?php

namespace App\Exports;

use App\Imports\AnimalImport;
use App\Models\Animal;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AnimalExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(private readonly array $filters = []) {}

    public function query(): Builder
    {
        return Animal::query()
            ->with(['breed', 'farm', 'paddock', 'dam', 'sire'])
            ->when($this->filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('ear_tag', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('microchip', 'like', "%{$search}%");
                });
            })
            ->when($this->filters['species'] ?? null, fn (Builder $query, string $species) => $query->where('species', $species))
            ->when($this->filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($this->filters['farm_id'] ?? null, fn (Builder $query, int $farmId) => $query->where('farm_id', $farmId))
            ->orderBy('id');
    }

    /**
     * @param  Animal  $animal
     * @return array<int, mixed>
     */
    public function map(mixed $animal): array
    {
        return array_map(fn (mixed $value): mixed => self::protectSpreadsheetFormula($value), [
            $animal->ear_tag,
            $animal->name,
            $animal->species,
            $animal->breed?->name,
            $animal->sex,
            $animal->identification_method,
            $animal->microchip,
            $animal->registration_number,
            $animal->birth_date?->format('Y-m-d'),
            $animal->birth_weight_kg,
            $animal->color,
            $animal->markings,
            $animal->purpose,
            $animal->status,
            $animal->reproductive_status,
            $animal->farm?->code,
            $animal->paddock?->code,
            $animal->location,
            $animal->dam?->ear_tag,
            $animal->sire?->ear_tag,
            $animal->acquisition_type,
            $animal->acquisition_date?->format('Y-m-d'),
            $animal->acquisition_cost,
            $animal->supplier_name,
            $animal->current_weight_kg,
            $animal->last_weighed_at?->format('Y-m-d'),
            $animal->health_notes,
            $animal->notes,
        ]);
    }

    /**
     * Prevents spreadsheet formula injection: text starting with = + - @ is executed by Excel.
     */
    public static function protectSpreadsheetFormula(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1
            ? "'".$value
            : $value;
    }

    public function headings(): array
    {
        return AnimalImport::DISPLAY_HEADINGS;
    }
}
