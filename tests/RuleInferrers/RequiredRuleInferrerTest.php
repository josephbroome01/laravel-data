<?php

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\RuleInferrers\RequiredRuleInferrer;
use Spatie\LaravelData\Support\DataClass;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Tests\Fakes\SimpleData;

beforeEach(function () {
    $this->inferrer = new RequiredRuleInferrer();
});

it('wont add a required rule when a property is non nullable', function () {
    $dataProperty = getProperty(new class () extends Data {
        public string $string;
    });

    $rules = $this->inferrer->handle($dataProperty, []);

    expect($rules)->toEqualCanonicalizing(['required']);
});

it('wont add a required rule when a property is nullable', function () {
    $dataProperty = getProperty(new class () extends Data {
        public ?string $string;
    });

    $rules = $this->inferrer->handle($dataProperty, []);

    expect($rules)->toEqualCanonicalizing([]);
});

it('wont add a required rule when a property already contains a required rule', function () {
    $dataProperty = getProperty(new class () extends Data {
        public string $string;
    });

    $rules = $this->inferrer->handle($dataProperty, ['required_if:bla']);

    expect($rules)->toEqualCanonicalizing(['required_if:bla']);
});

it('wont add a required rule when a property already contains a required object rule', function () {
    $dataProperty = getProperty(new class () extends Data {
        public string $string;
    });

    $rules = $this->inferrer->handle($dataProperty, [Rule::requiredIf(true)]);

    expect($rules)->toEqualCanonicalizing([Rule::requiredIf(true)]);
});

it('wont add a required rule when a property already contains a boolean rule', function () {
    $dataProperty = getProperty(new class () extends Data {
        public string $string;
    });

    $rules = $this->inferrer->handle($dataProperty, ['boolean']);

    expect($rules)->toEqualCanonicalizing(['boolean']);
});

it('wont add a required rule when a property already contains a nullable rule', function () {
    $dataProperty = getProperty(new class () extends Data {
        public string $string;
    });

    $rules = $this->inferrer->handle($dataProperty, ['nullable']);

    expect($rules)->toEqualCanonicalizing(['nullable']);
});

it('has support for rules that cannot be converted to string', function () {
    $dataProperty = getProperty(new class () extends Data {
        public string $string;
    });

    $rules = $this->inferrer->handle($dataProperty, [new Enum('SomeClass')]);

    expect($rules)->toEqualCanonicalizing(['required', new Enum('SomeClass')]);
});

it('wont add required to a data collection since it is already present', function () {
    $dataProperty = getProperty(new class () extends Data {
        #[DataCollectionOf(SimpleData::class)]
        public DataCollection $collection;
    });

    $rules = $this->inferrer->handle($dataProperty, ['present', 'array']);

    expect($rules)->toEqualCanonicalizing(['present', 'array']);
});

// Helpers
function getProperty(object $class): DataProperty
{
    $dataClass = DataClass::create(new ReflectionClass($class));

    return $dataClass->properties()[0];
}
