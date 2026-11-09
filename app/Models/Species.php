<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Catálogo de especies. Los animales guardan el código (bovine, goat…) en animals.species,
 * por eso el código no cambia una vez creado.
 */
class Species extends Model
{
    public const CACHE_KEY = 'catalogs.species.options';

    protected $table = 'species';

    protected $fillable = ['code', 'name', 'description'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function breeds(): HasMany
    {
        return $this->hasMany(Breed::class);
    }

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class, 'species', 'code');
    }

    /**
     * Opciones código => nombre para selects, validaciones y etiquetas.
     * Si el catálogo aún no existe (instalación sin migrar) usa las especies base.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        try {
            $options = Cache::get(self::CACHE_KEY);

            if (! is_array($options) || $options === []) {
                $options = Schema::hasTable('species') ? static::query()->orderBy('id')->pluck('name', 'code')->all() : [];

                if ($options !== []) {
                    Cache::forever(self::CACHE_KEY, $options);
                }
            }
        } catch (Throwable) {
            $options = [];
        }

        return $options !== [] ? $options : Animal::SPECIES;
    }

    public static function label(?string $code): string
    {
        return self::options()[$code] ?? ($code ? 'Otro' : '—');
    }
}
