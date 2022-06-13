<?php

use Illuminate\Contracts\Support\Arrayable;
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
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

it('works with non typed properties', function () {
    $helper = resolveHelper(new class () {
        public $property;
    });

    expect($helper->isLazy())->toBeFalse();
    expect($helper->isNullable())->toBeTrue();
    expect($helper->isData())->toBeFalse();
    expect($helper->isDataCollection())->toBeFalse();
    expect($helper->types()->isEmpty())->toBeTrue();
    expect($helper->name())->toEqual('property');
    expect($helper->validationAttributes())->toEqual([]);
});

it('can check if a property is lazy', function () {
    $helper = resolveHelper(new class () {
        public int $property;
    });

    expect($helper->isLazy())->toBeFalse();

    $helper = resolveHelper(new class () {
        public int|Lazy $property;
    });

    expect($helper->isLazy())->toBeTrue();

    $helper = resolveHelper(new class () {
        public int|Lazy|null $property;
    });

    expect($helper->isLazy())->toBeTrue();
});

it('can check if a property is nullable', function () {
    $helper = resolveHelper(new class () {
        public int $property;
    });

    expect($helper->isNullable())->toBeFalse();

    $helper = resolveHelper(new class () {
        public ?int $property;
    });

    expect($helper->isNullable())->toBeTrue();

    $helper = resolveHelper(new class () {
        public null|int $property;
    });

    expect($helper->isNullable())->toBeTrue();
});

it('can check if a property is a data object', function () {
    $helper = resolveHelper(new class () {
        public int $property;
    });

    expect($helper->isData())->toBeFalse();

    $helper = resolveHelper(new class () {
        public SimpleData $property;
    });

    expect($helper->isData())->toBeTrue();

    $helper = resolveHelper(new class () {
        public SimpleData|Lazy $property;
    });

    expect($helper->isData())->toBeTrue();
});

it('can check if a property is a data collection', function () {
    $helper = resolveHelper(new class () {
        public int $property;
    });

    expect($helper->isDataCollection())->toBeFalse();

    $helper = resolveHelper(new class () {
        public DataCollection $property;
    });

    expect($helper->isDataCollection())->toBeTrue();

    $helper = resolveHelper(new class () {
        public DataCollection|Lazy $property;
    });

    expect($helper->isDataCollection())->toBeTrue();
});

it('can get the correct types for the property', function () {
    $helper = resolveHelper(new class () {
        public int $property;
    });

    expect($helper->types()->all())->toEqual(['int']);

    $helper = resolveHelper(new class () {
        public int|float $property;
    });

    expect($helper->types()->all())->toEqual(['int', 'float']);

    $helper = resolveHelper(new class () {
        public int|Lazy $property;
    });

    expect($helper->types()->all())->toEqual(['int']);

    $helper = resolveHelper(new class () {
        public int|Lazy|null $property;
    });

    expect($helper->types()->all())->toEqual(['int']);
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

    expect($helper->validationAttributes())->toEqual([new Max(10)]);
});

it('can get the cast attribute', function () {
    $helper = resolveHelper(new class () {
        #[WithCast(DateTimeInterfaceCast::class)]
        public SimpleData $property;
    });

    expect($helper->castAttribute())->toEqual(new WithCast(DateTimeInterfaceCast::class));
});

it('can get the cast attribute with arguments', function () {
    $helper = resolveHelper(new class () {
        #[WithCast(DateTimeInterfaceCast::class, 'd-m-y')]
        public SimpleData $property;
    });

    expect($helper->castAttribute())->toEqual(new WithCast(DateTimeInterfaceCast::class, 'd-m-y'));
});

it('can get the transformer attribute', function () {
    $helper = resolveHelper(new class () {
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        public SimpleData $property;
    });

    expect($helper->transformerAttribute())->toEqual(new WithTransformer(DateTimeInterfaceTransformer::class));
});

it('can get the transformer attribute with arguments', function () {
    $helper = resolveHelper(new class () {
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'd-m-y')]
        public SimpleData $property;
    });

    expect($helper->transformerAttribute())->toEqual(new WithTransformer(DateTimeInterfaceTransformer::class, 'd-m-y'));
});

it('can get the data class for a data object', function () {
    $helper = resolveHelper(new class () {
        public SimpleData $property;
    });

    expect($helper->dataClassName())->toEqual(SimpleData::class);
});

it('has support for intersection types', function () {
    $this->onlyPHP81();

    $dataProperty = DataProperty::create(new ReflectionProperty(IntersectionTypeData::class, 'intersection'));

    expect($dataProperty->types())->toEqual(new DataPropertyTypes([Arrayable::class, Countable::class]));
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

    expect($dataProperty->dataClassName())->toEqual($expected);
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
