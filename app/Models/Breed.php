<?php

namespace App\Models;

use Database\Factories\BreedFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Breed extends Model
{
    /** @use HasFactory<BreedFactory> */
    use HasFactory;

    protected $fillable = [
        'species',
        'name',
        'origin',
        'description',
    ];

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class);
    }
}
