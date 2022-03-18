<?php

namespace Spatie\LaravelData\Mappers;

use Illuminate\Support\Str;

class SnakeToCamelCaseNameMapper implements NameMapper
{
    public function map(int|string $name): string|int
    {
        if (! is_string($name)) {
            return $name;
        }

        return Str::snake($name);
    }

    public function inverse(): NameMapper
    {
        return new CamelToSnakeCaseNameMapper();
    }
}
