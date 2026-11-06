<?php

namespace App\Models\Concerns;

use App\Models\Scopes\FarmAccessScope;

trait ScopesToAccessibleFarms
{
    protected static function bootScopesToAccessibleFarms(): void
    {
        static::addGlobalScope(new FarmAccessScope);
    }
}
