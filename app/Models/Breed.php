<?php

namespace App\Models;

use Database\Factories\BreedFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Breed extends Model
{
    /** @use HasFactory<BreedFactory> */
    use HasFactory;

    protected $fillable = [
        'species_id',
        'species',
        'name',
        'origin',
        'description',
    ];

    /**
     * Mantiene sincronizados species_id (relación) y species (código usado por los animales
     * y por las validaciones existentes).
     */
    protected static function booted(): void
    {
        static::saving(function (self $breed): void {
            if ($breed->species_id && ($breed->isDirty('species_id') || blank($breed->species))) {
                $breed->species = Species::query()->whereKey($breed->species_id)->value('code') ?? $breed->species;
            } elseif (blank($breed->species_id) && filled($breed->species)) {
                $breed->species_id = Species::query()->where('code', $breed->species)->value('id');
            }
        });
    }

    public function speciesCatalog(): BelongsTo
    {
        return $this->belongsTo(Species::class, 'species_id');
    }

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class);
    }
}
