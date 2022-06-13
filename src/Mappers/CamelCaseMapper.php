<?php

namespace Spatie\LaravelData\Mappers;

use Illuminate\Support\Str;

class CamelCaseMapper implements NameMapper
{
    public function map(int|string $name): string|int
    {
        return  is_string($name)
            ? Str::camel($name)
            : $name;
    }
}
