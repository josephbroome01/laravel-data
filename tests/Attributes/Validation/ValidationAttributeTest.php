<?php

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\ValidationAttribute;
use Spatie\LaravelData\Tests\TestCase;

uses(TestCase::class);

it('can get a string representation of rules', function () {
    $rule = new Rule('string', 'uuid', 'required');

    expect((string) $rule)->toEqual('string|uuid|required');
});

it('can normalize values', function (mixed $input, mixed $output) {
    $normalizer = new class () extends ValidationAttribute {
        public function execute(mixed $value): mixed
        {
            return $this->normalizeValue($value);
        }

        public function getRules(): array
        {
            return [];
        }
    };

    expect($normalizer->execute($input))->toEqual($output);
})->with('values');

// Datasets
dataset('values', function () {
    yield [
        'input' => 'Hello world',
        'output' => 'Hello world',
    ];

    yield [
        'input' => 42,
        'output' => '42',
    ];

    yield [
        'input' => 3.14,
        'output' => '3.14',
    ];

    yield [
        'input' => true,
        'output' => 'true',
    ];

    yield [
        'input' => false,
        'output' => 'false',
    ];

    yield [
        'input' => ['a', 'b', 'c'],
        'output' => 'a,b,c',
    ];

    yield [
        'input' => CarbonImmutable::create(2020, 05, 16, 0, 0, 0, new DateTimeZone('Europe/Brussels')),
        'output' => '2020-05-16T00:00:00+02:00',
    ];
});
