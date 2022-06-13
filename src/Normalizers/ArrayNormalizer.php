<?php

namespace Spatie\LaravelData\Normalizers;

class ArrayNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        return is_array($value)
            ? $value
            : null;
    }
}
