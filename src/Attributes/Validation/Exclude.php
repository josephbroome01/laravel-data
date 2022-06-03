<?php

namespace Spatie\LaravelData\Attributes\Validation;

use Attribute;
use Illuminate\Validation\Rules\ExcludeIf;
use Illuminate\Validation\Rules\RequiredIf;
use Spatie\LaravelData\Support\Validation\Rules\FoundationExcludeIf;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Exclude extends ValidationAttribute
{
    public function __construct(private ?ExcludeIf $rule = null)
    {
    }

    public function getRules(): array
    {
        return [$this->rule ?? static::keyword()];
    }

    public static function keyword(): string
    {
        return 'exclude';
    }

    public static function create(string ...$parameters): static
    {
        return new self();
    }
}