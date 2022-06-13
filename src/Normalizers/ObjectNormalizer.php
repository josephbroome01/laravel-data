<?php

namespace Spatie\LaravelData\Normalizers;

use stdClass;

class ObjectNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        return $value instanceof stdClass
            ? (array)$value
            : null;
    }
}
