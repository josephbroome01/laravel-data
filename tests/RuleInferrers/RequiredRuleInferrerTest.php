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
use Spatie\LaravelData\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->inferrer = new RequiredRuleInferrer();
});

it('wont add a required rule when a property is non nullable', function () {
    $dataProperty = getProperty(new class () extends Data {
        public string $string;
    });

    $rules = $this->inferrer->handle($dataProperty, []);

    $this->assertEqualsCanonicalizing(['required'], $rules);
});

it('wont add a required rule when a property is nullable', function () {
    $dataProperty = getProperty(new class () extends Data {
        public ?string $string;
    });

    $rules = $this->inferrer->handle($dataProperty, []);

    $this->assertEqualsCanonicalizing([], $rules);
});

it('wont add a required rule when a property already contains a required rule', function () {
    $dataProperty = getProperty(new class () extends Data {
        public string $string;
    });

    $rules = $this->inferrer->handle($dataProperty, ['required_if:bla']);

    $this->assertEqualsCanonicalizing(['required_if:bla'], $rules);
});

it('wont add a required rule when a property already contains a required object rule', function () {
    $dataProperty = getProperty(new class () extends Data {
        public string $string;
    });

    $rules = $this->inferrer->handle($dataProperty, [Rule::requiredIf(true)]);

    $this->assertEqualsCanonicalizing([Rule::requiredIf(true)], $rules);
});

it('wont add a required rule when a property already contains a boolean rule', function () {
    $dataProperty = getProperty(new class () extends Data {
        public string $string;
    });

    $rules = $this->inferrer->handle($dataProperty, ['boolean']);

    $this->assertEqualsCanonicalizing(['boolean'], $rules);
});

it('wont add a required rule when a property already contains a nullable rule', function () {
    $dataProperty = getProperty(new class () extends Data {
        public string $string;
    });

    $rules = $this->inferrer->handle($dataProperty, ['nullable']);

    $this->assertEqualsCanonicalizing(['nullable'], $rules);
});

it('has support for rules that cannot be converted to string', function () {
    $dataProperty = getProperty(new class () extends Data {
        public string $string;
    });

    $rules = $this->inferrer->handle($dataProperty, [new Enum('SomeClass')]);

    $this->assertEqualsCanonicalizing(['required', new Enum('SomeClass')], $rules);
});

it('wont add required to a data collection since it is already present', function () {
    $dataProperty = getProperty(new class () extends Data {
        #[DataCollectionOf(SimpleData::class)]
        public DataCollection $collection;
    });

    $rules = $this->inferrer->handle($dataProperty, ['present', 'array']);

    $this->assertEqualsCanonicalizing(['present', 'array'], $rules);
});

// Helpers
function getProperty(object $class): DataProperty
{
    $dataClass = DataClass::create(new ReflectionClass($class));

    return $dataClass->properties()[0];
}
