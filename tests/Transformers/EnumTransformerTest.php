<?php

use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Tests\Fakes\DummyBackedEnum;
use Spatie\LaravelData\Transformers\EnumTransformer;

it('can transform enums', function () {
    $this->onlyPHP81();

    $transformer = new EnumTransformer();

    $class = new class () {
        public DummyBackedEnum $enum = DummyBackedEnum::FOO;
    };

    $this->assertEquals(
        DummyBackedEnum::FOO->value,
        $transformer->transform(DataProperty::create(new ReflectionProperty($class, 'enum')), $class->enum)
    );
});
