<?php

use Illuminate\Validation\Rules\Enum as EnumRule;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\RequiredWith;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Resolvers\DataPropertyValidationRulesResolver;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Tests\Fakes\FakeEnum;
use Spatie\LaravelData\Tests\Fakes\NestedData;
use Spatie\LaravelData\Tests\Fakes\SimpleData;
use Spatie\LaravelData\Tests\TestCase;


it('will add a required or nullable rule based upon the property nullability', function () {
    $rules = resolveRules(new class () {
        public int $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['required', 'numeric'],
    ], $rules);

    $rules = resolveRules(new class () {
        public ?int $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['nullable', 'numeric'],
    ], $rules);
});

it('will add basic rules for certain types', function () {
    $rules = resolveRules(new class () {
        public string $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['required', 'string'],
    ], $rules);

    $rules = resolveRules(new class () {
        public int $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['required', 'numeric'],
    ], $rules);

    $rules = resolveRules(new class () {
        public bool $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['boolean'],
    ], $rules);

    $rules = resolveRules(new class () {
        public float $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['required', 'numeric'],
    ], $rules);

    $rules = resolveRules(new class () {
        public array $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['required', 'array'],
    ], $rules);
});

it('will add rules for enums', function () {
    $this->onlyPHP81();

    $rules = resolveRules(new class () {
        public FakeEnum $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['required', new EnumRule(FakeEnum::class)],
    ], $rules);
});

it('will take validation attributes into account', function () {
    $rules = resolveRules(new class () {
        #[Max(10)]
        public string $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['required', 'string', 'max:10'],
    ], $rules);
});

it('will take rules from nested data objects', function () {
    $rules = resolveRules(new class () {
        public SimpleData $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['required', 'array'],
        'property.string' => ['required', 'string'],
    ], $rules);

    $rules = resolveRules(new class () {
        public ?SimpleData $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['nullable', 'array'],
        'property.string' => ['nullable', 'string'],
    ], $rules);
});

it('will take rules from nested data collections', function () {
    $rules = resolveRules(new class () {
        /** @var \Spatie\LaravelData\Tests\Fakes\SimpleData[] */
        public DataCollection $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['present', 'array'],
        'property.*.string' => ['required', 'string'],
    ], $rules);

    $rules = resolveRules(new class () {
        /** @var \Spatie\LaravelData\Tests\Fakes\SimpleData[]|null */
        public ?DataCollection $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['nullable', 'array'],
        'property.*.string' => ['required', 'string'],
    ], $rules);
});

it('can nest validation rules event further', function () {
    $rules = resolveRules(new class () {
        public NestedData $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['required', 'array'],
        'property.simple' => ['required', 'array'],
        'property.simple.string' => ['required', 'string'],
    ], $rules);

    $rules = resolveRules(new class () {
        public ?SimpleData $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['nullable', 'array'],
        'property.string' => ['nullable', 'string'],
    ], $rules);
});

it('will never add extra require rules when not needed', function () {
    $rules = resolveRules(new class () {
        public ?string $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['string', 'nullable'],
    ], $rules);

    $rules = resolveRules(new class () {
        public bool $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['boolean'],
    ], $rules);

    $rules = resolveRules(new class () {
        #[RequiredWith('other')]
        public string $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['string', 'required_with:other'],
    ], $rules);

    $rules = resolveRules(new class () {
        #[Rule('required_with:other')]
        public string $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['string', 'required_with:other'],
    ], $rules);
});

it('will work with non string rules', function () {
    $rules = resolveRules(new class () {
        #[Enum(FakeEnum::class)]
        public string $property;
    });

    $this->assertEqualsCanonicalizing([
        'property' => ['string', 'required', new EnumRule(FakeEnum::class)],
    ], $rules);
});

// Helpers
function resolveRules(object $class): array
{
    $reflectionProperty = new ReflectionProperty($class, 'property');

    $property = DataProperty::create($reflectionProperty);

    return app(DataPropertyValidationRulesResolver::class)->execute($property)->toArray();
}
