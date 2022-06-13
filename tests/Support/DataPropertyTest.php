<?php

use Countable;
use Generator;
use Illuminate\Contracts\Support\Arrayable;
use ReflectionProperty;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithoutValidation;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Exceptions\CannotFindDataTypeForProperty;
use Spatie\LaravelData\Exceptions\InvalidDataPropertyType;
use Spatie\LaravelData\Lazy;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\DataPropertyTypes;
use Spatie\LaravelData\Tests\Fakes\CollectionAnnotationsData;
use Spatie\LaravelData\Tests\Fakes\IntersectionTypeData;
use Spatie\LaravelData\Tests\Fakes\SimpleData;
use Spatie\LaravelData\Tests\TestCase;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

uses(TestCase::class);

it('works with non typed properties', function () {
    $helper = resolveHelper(new class () {
        public $property;
    });

    $this->assertFalse($helper->isLazy());
    $this->assertTrue($helper->isNullable());
    $this->assertFalse($helper->isData());
    $this->assertFalse($helper->isDataCollection());
    $this->assertTrue($helper->types()->isEmpty());
    $this->assertEquals('property', $helper->name());
    $this->assertEquals([], $helper->validationAttributes());
});

it('can check if a property is lazy', function () {
    $helper = resolveHelper(new class () {
        public int $property;
    });

    $this->assertFalse($helper->isLazy());

    $helper = resolveHelper(new class () {
        public int|Lazy $property;
    });

    $this->assertTrue($helper->isLazy());

    $helper = resolveHelper(new class () {
        public int|Lazy|null $property;
    });

    $this->assertTrue($helper->isLazy());
});

it('can check if a property is nullable', function () {
    $helper = resolveHelper(new class () {
        public int $property;
    });

    $this->assertFalse($helper->isNullable());

    $helper = resolveHelper(new class () {
        public ?int $property;
    });

    $this->assertTrue($helper->isNullable());

    $helper = resolveHelper(new class () {
        public null|int $property;
    });

    $this->assertTrue($helper->isNullable());
});

it('can check if a property is a data object', function () {
    $helper = resolveHelper(new class () {
        public int $property;
    });

    $this->assertFalse($helper->isData());

    $helper = resolveHelper(new class () {
        public SimpleData $property;
    });

    $this->assertTrue($helper->isData());

    $helper = resolveHelper(new class () {
        public SimpleData|Lazy $property;
    });

    $this->assertTrue($helper->isData());
});

it('can check if a property is a data collection', function () {
    $helper = resolveHelper(new class () {
        public int $property;
    });

    $this->assertFalse($helper->isDataCollection());

    $helper = resolveHelper(new class () {
        public DataCollection $property;
    });

    $this->assertTrue($helper->isDataCollection());

    $helper = resolveHelper(new class () {
        public DataCollection|Lazy $property;
    });

    $this->assertTrue($helper->isDataCollection());
});

it('can get the correct types for the property', function () {
    $helper = resolveHelper(new class () {
        public int $property;
    });

    $this->assertEquals(['int'], $helper->types()->all());

    $helper = resolveHelper(new class () {
        public int|float $property;
    });

    $this->assertEquals(['int', 'float'], $helper->types()->all());

    $helper = resolveHelper(new class () {
        public int|Lazy $property;
    });

    $this->assertEquals(['int'], $helper->types()->all());

    $helper = resolveHelper(new class () {
        public int|Lazy|null $property;
    });

    $this->assertEquals(['int'], $helper->types()->all());
});

it('cannot combine a data object and another type', function () {
    $this->expectException(InvalidDataPropertyType::class);

    resolveHelper(new class () {
        public SimpleData|int $property;
    });
});

it('cannot combine a data collection and another type', function () {
    $this->expectException(InvalidDataPropertyType::class);

    resolveHelper(new class () {
        public DataCollection|int $property;
    });
});

it('can get validation attributes', function () {
    $helper = resolveHelper(new class () {
        #[Max(10)]
        public SimpleData $property;
    });

    $this->assertEquals([new Max(10)], $helper->validationAttributes());
});

it('can get the cast attribute', function () {
    $helper = resolveHelper(new class () {
        #[WithCast(DateTimeInterfaceCast::class)]
        public SimpleData $property;
    });

    $this->assertEquals(new WithCast(DateTimeInterfaceCast::class), $helper->castAttribute());
});

it('can get the cast attribute with arguments', function () {
    $helper = resolveHelper(new class () {
        #[WithCast(DateTimeInterfaceCast::class, 'd-m-y')]
        public SimpleData $property;
    });

    $this->assertEquals(new WithCast(DateTimeInterfaceCast::class, 'd-m-y'), $helper->castAttribute());
});

it('can get the transformer attribute', function () {
    $helper = resolveHelper(new class () {
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        public SimpleData $property;
    });

    $this->assertEquals(new WithTransformer(DateTimeInterfaceTransformer::class), $helper->transformerAttribute());
});

it('can get the transformer attribute with arguments', function () {
    $helper = resolveHelper(new class () {
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'd-m-y')]
        public SimpleData $property;
    });

    $this->assertEquals(new WithTransformer(DateTimeInterfaceTransformer::class, 'd-m-y'), $helper->transformerAttribute());
});

it('can get the data class for a data object', function () {
    $helper = resolveHelper(new class () {
        public SimpleData $property;
    });

    $this->assertEquals(SimpleData::class, $helper->dataClassName());
});

it('has support for intersection types', function () {
    $this->onlyPHP81();

    $dataProperty = DataProperty::create(new ReflectionProperty(IntersectionTypeData::class, 'intersection'));

    $this->assertEquals(new DataPropertyTypes([Arrayable::class, Countable::class]), $dataProperty->types());
});

it('can check if a property should be validated', function () {
    $this->assertTrue(resolveHelper(new class () {
        public string $property;
    })->shouldValidateProperty());

    $this->assertFalse(resolveHelper(new class () {
        #[WithoutValidation]
        public string $property;
    })->shouldValidateProperty());
});

it('can get the data class for a data collection by annotation', function (
    string $property,
    ?string $expected
) {
    $dataProperty = DataProperty::create(new ReflectionProperty(CollectionAnnotationsData::class, $property));

    $this->assertEquals($expected, $dataProperty->dataClassName());
})->with('correctAnnotations');

it('cannot get the data class for invalid annotations', function (
    string $property,
) {
    $dataProperty = DataProperty::create(new ReflectionProperty(CollectionAnnotationsData::class, $property));

    $this->expectException(CannotFindDataTypeForProperty::class);

    $dataProperty->dataClassName();
})->with('invalidAnnotations');

// Datasets
dataset('correctAnnotations', function () {
    yield [
        'property' => 'propertyA',
        'expected' => SimpleData::class,
    ];

    yield [
        'property' => 'propertyB',
        'expected' => SimpleData::class,
    ];

    yield [
        'property' => 'propertyC',
        'expected' => SimpleData::class,
    ];

    yield [
        'property' => 'propertyD',
        'expected' => SimpleData::class,
    ];

    yield [
        'property' => 'propertyE',
        'expected' => SimpleData::class,
    ];

    yield [
        'property' => 'propertyF',
        'expected' => SimpleData::class,
    ];

    yield [
        'property' => 'propertyG',
        'expected' => SimpleData::class,
    ];

    yield [
        'property' => 'propertyH',
        'expected' => SimpleData::class,
    ];

    yield [
        'property' => 'propertyI',
        'expected' => SimpleData::class,
    ];

    yield [
        'property' => 'propertyJ',
        'expected' => SimpleData::class,
    ];

    yield [
        'property' => 'propertyK',
        'expected' => SimpleData::class,
    ];

    yield [
        'property' => 'propertyL',
        'expected' => SimpleData::class,
    ];
});

dataset('invalidAnnotations', function () {
    yield [
        'property' => 'propertyM',
    ];

    yield [
        'property' => 'propertyN',
    ];

    yield [
        'property' => 'propertyO',
    ];
});

// Helpers
function resolveHelper(object $class): DataProperty
{
    $reflectionProperty = new ReflectionProperty($class, 'property');

    return DataProperty::create($reflectionProperty);
}
