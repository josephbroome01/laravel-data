<?php

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Exceptions\DataPropertyCanOnlyHaveOneType;
use Spatie\LaravelData\Lazy;
use Spatie\LaravelData\Resolvers\EmptyDataResolver;
use Spatie\LaravelData\Tests\Fakes\SimpleData;
use Spatie\LaravelData\Tests\TestCase;

uses(TestCase::class);

it('will return null if the property has no type', function () {
    assertEmptyPropertyValue(null, new class () {
        public $property;
    });
});

it('will return null if the property has a basic type', function () {
    assertEmptyPropertyValue(null, new class () {
        public int $property;
    });

    assertEmptyPropertyValue(null, new class () {
        public bool $property;
    });

    assertEmptyPropertyValue(null, new class () {
        public float $property;
    });

    assertEmptyPropertyValue(null, new class () {
        public string $property;
    });

    assertEmptyPropertyValue(null, new class () {
        public mixed $property;
    });
});

it('will return an array for collection types', function () {
    assertEmptyPropertyValue([], new class () {
        public array $property;
    });

    assertEmptyPropertyValue([], new class () {
        public Collection $property;
    });

    assertEmptyPropertyValue([], new class () {
        public EloquentCollection $property;
    });

    assertEmptyPropertyValue([], new class () {
        public DataCollection $property;
    });
});

it('will further transform resources', function () {
    assertEmptyPropertyValue(['string' => null], new class () {
        public SimpleData $property;
    });
});

it('will return the base type for lazy types', function () {
//        assertEmptyPropertyValue(null, new class() {
//            public Lazy | string $property;
//        });

    assertEmptyPropertyValue([], new class () {
        public Lazy | array $property;
    });

    assertEmptyPropertyValue(['string' => null], new class () {
        public Lazy | SimpleData $property;
    });
});

it('will return the base type for lazy types that can be null', function () {
    assertEmptyPropertyValue(null, new class () {
        public Lazy | string | null $property;
    });

    assertEmptyPropertyValue([], new class () {
        public Lazy | array | null $property;
    });

    assertEmptyPropertyValue(['string' => null], new class () {
        public Lazy | SimpleData | null $property;
    });
});

it('cannot have multiple types', function () {
    $this->expectException(DataPropertyCanOnlyHaveOneType::class);

    assertEmptyPropertyValue(null, new class () {
        public int | string $property;
    });
});

it('cannot have multiple types with a lazy', function () {
    $this->expectException(DataPropertyCanOnlyHaveOneType::class);

    assertEmptyPropertyValue(null, new class () {
        public int | string | Lazy $property;
    });
});

it('cannot have multiple types with a nullable lazy', function () {
    $this->expectException(DataPropertyCanOnlyHaveOneType::class);

    assertEmptyPropertyValue(null, new class () {
        public int | string | Lazy | null $property;
    });
});

it('can overwrite empty properties', function () {
    assertEmptyPropertyValue('Hello', new class () {
        public string $property;
    }, ['property' => 'Hello']);
});

it('can use the property default value', function () {
    assertEmptyPropertyValue('Hello', new class () {
        public string $property = 'Hello';
    });
});

it('can use the constructor property default value', function () {
    assertEmptyPropertyValue('Hello', new class () {
        public function __construct(
            public string $property = 'Hello',
        ) {
        }
    });
});

// Helpers
function assertEmptyPropertyValue(mixed $expected, object $class, array $extra = [])
{
    $resolver = app(EmptyDataResolver::class);

    expect($resolver->execute($class::class, $extra)['property'])->toEqual($expected);
}
