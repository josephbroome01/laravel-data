<?php

use Illuminate\Validation\Rules\Password as ValidationPassword;
use Spatie\LaravelData\Attributes\Validation\Password;
use Spatie\LaravelData\Tests\TestCase;

uses(TestCase::class);

test('password rule returns preconfigured password validations', function (callable $setDefaults, array $expectedConfig) {
    ValidationPassword::$defaultCallback = null;
    $setDefaults();

    [$rule] = (new Password(default: true))->getRules();
    $clazz = new ReflectionClass($rule);

    foreach ($expectedConfig as $key => $expected) {
        $prop = $clazz->getProperty($key);
        $prop->setAccessible(true);
        $actual = $prop->getValue($rule);

        $this->assertSame($expected, $actual);
    }
})->with('preconfiguredPasswordValidationsProvider');

// Datasets
dataset('preconfiguredPasswordValidationsProvider', function () {
    yield 'min length set to 42' => [
        'setDefaults' => fn () => ValidationPassword::defaults(fn () => ValidationPassword::min(42)),
        'expectedConfig' => [
            'min' => 42,
        ],
    ];

    yield 'unconfigured' => [
        'setDefaults' => fn () => null,
        'expectedConfig' => [
            'min' => 8,
        ],
    ];

    yield 'uncompromised' => [
        'setDefaults' => fn () => ValidationPassword::defaults(fn () => ValidationPassword::min(69)->uncompromised(7)),
        'expectedConfig' => [
            'min' => 69,
            'uncompromised' => true,
            'compromisedThreshold' => 7,
        ],
    ];
});
