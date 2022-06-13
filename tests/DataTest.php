<?php

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Lazy;
use Spatie\LaravelData\Tests\Factories\DataBlueprintFactory;
use Spatie\LaravelData\Tests\Factories\DataPropertyBlueprintFactory;
use Spatie\LaravelData\Tests\Fakes\Casts\ConfidentialDataCast;
use Spatie\LaravelData\Tests\Fakes\Casts\ConfidentialDataCollectionCast;
use Spatie\LaravelData\Tests\Fakes\Casts\StringToUpperCast;
use Spatie\LaravelData\Tests\Fakes\DefaultLazyData;
use Spatie\LaravelData\Tests\Fakes\DummyDto;
use Spatie\LaravelData\Tests\Fakes\DummyModel;
use Spatie\LaravelData\Tests\Fakes\EmptyData;
use Spatie\LaravelData\Tests\Fakes\FakeModelData;
use Spatie\LaravelData\Tests\Fakes\FakeNestedModelData;
use Spatie\LaravelData\Tests\Fakes\IntersectionTypeData;
use Spatie\LaravelData\Tests\Fakes\LazyData;
use Spatie\LaravelData\Tests\Fakes\Models\FakeNestedModel;
use Spatie\LaravelData\Tests\Fakes\MultiLazyData;
use Spatie\LaravelData\Tests\Fakes\ReadonlyData;
use Spatie\LaravelData\Tests\Fakes\RequestData;
use Spatie\LaravelData\Tests\Fakes\SimpleData;
use Spatie\LaravelData\Tests\Fakes\SimpleDataWithoutConstructor;
use Spatie\LaravelData\Tests\Fakes\Transformers\ConfidentialDataCollectionTransformer;
use Spatie\LaravelData\Tests\Fakes\Transformers\ConfidentialDataTransformer;
use Spatie\LaravelData\Tests\Fakes\Transformers\StringToUpperTransformer;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\LaravelData\WithData;

uses(TestCase::class);

it('can create a resource', function () {
    $dataClass = DataBlueprintFactory::new()->withProperty(
        DataPropertyBlueprintFactory::new('string')->withType('string')
    )->create();

    $data = new $dataClass('Ruben');

    $this->assertEquals([
        'string' => 'Ruben',
    ], $data->toArray());
});

it('can create a collection of resources', function () {
    $collection = SimpleData::collection(collect([
        'Ruben',
        'Freek',
        'Brent',
    ]));

    $this->assertEquals([
        ['string' => 'Ruben'],
        ['string' => 'Freek'],
        ['string' => 'Brent'],
    ], $collection->toArray());
});

it('can include a lazy property', function () {
    $dataClass = DataBlueprintFactory::new()->withProperty(
        DataPropertyBlueprintFactory::new('name')->lazy()->withType('string')
    )->create();

    $data = new $dataClass(Lazy::create(fn () => 'test'));

    expect($data->toArray())->toEqual([]);

    $this->assertEquals([
        'name' => 'test',
    ], $data->include('name')->toArray());
});

it('can have a pre filled in lazy property', function () {
    $dataClass = DataBlueprintFactory::new()->withProperty(
        DataPropertyBlueprintFactory::new('name')->lazy()->withType('string')
    )->create();

    $data = new $dataClass('test');

    $this->assertEquals([
        'name' => 'test',
    ], $data->toArray());

    $this->assertEquals([
        'name' => 'test',
    ], $data->include('name')->toArray());
});

it('can include a nested lazy property', function () {
    $dataClass = DataBlueprintFactory::new()->withProperty(
        DataPropertyBlueprintFactory::new('data')->lazy()->withType(LazyData::class),
        DataPropertyBlueprintFactory::dataCollection('collection', LazyData::class)->lazy()
    )->create();

    $data = new $dataClass(
        Lazy::create(fn () => LazyData::from('Hello')),
        Lazy::create(fn () => LazyData::collection(['is', 'it', 'me', 'your', 'looking', 'for',])),
    );

    expect((clone $data)->toArray())->toEqual([]);

    $this->assertEquals([
        'data' => [],
    ], (clone $data)->include('data')->toArray());

    $this->assertEquals([
        'data' => ['name' => 'Hello'],
    ], (clone $data)->include('data.name')->toArray());

    $this->assertEquals([
        'collection' => [
            [],
            [],
            [],
            [],
            [],
            [],
        ],
    ], (clone $data)->include('collection')->toArray());

    $this->assertEquals([
        'collection' => [
            ['name' => 'is'],
            ['name' => 'it'],
            ['name' => 'me'],
            ['name' => 'your'],
            ['name' => 'looking'],
            ['name' => 'for'],
        ],
    ], (clone $data)->include('collection.name')->toArray());
});

it('can include specific nested data', function () {
    $dataClass = DataBlueprintFactory::new()->withProperty(
        DataPropertyBlueprintFactory::dataCollection('songs', MultiLazyData::class)->lazy()
    )->create();

    $collection = Lazy::create(fn () => MultiLazyData::collection([
        DummyDto::rick(),
        DummyDto::bon(),
    ]));

    $data = new $dataClass($collection);

    $this->assertEquals([
        'songs' => [
            ['name' => DummyDto::rick()->name],
            ['name' => DummyDto::bon()->name],
        ],
    ], $data->include('songs.name')->toArray());

    $this->assertEquals([
        'songs' => [
            [
                'name' => DummyDto::rick()->name,
                'artist' => DummyDto::rick()->artist,
            ],
            [
                'name' => DummyDto::bon()->name,
                'artist' => DummyDto::bon()->artist,
            ],
        ],
    ], $data->include('songs.{name,artist}')->toArray());

    $this->assertEquals([
        'songs' => [
            [
                'name' => DummyDto::rick()->name,
                'artist' => DummyDto::rick()->artist,
                'year' => DummyDto::rick()->year,
            ],
            [
                'name' => DummyDto::bon()->name,
                'artist' => DummyDto::bon()->artist,
                'year' => DummyDto::bon()->year,
            ],
        ],
    ], $data->include('songs.*')->toArray());
});

it('can have conditional lazy data', function () {
    $blueprint = new class () extends Data {
        public function __construct(
            public string|Lazy|null $name = null
        ) {
        }

        public static function create(string $name): static
        {
            return new self(
                Lazy::when(fn () => $name === 'Ruben', fn () => $name)
            );
        }
    };

    $data = $blueprint::create('Freek');

    expect($data->toArray())->toEqual([]);

    $data = $blueprint::create('Ruben');

    expect($data->toArray())->toEqual(['name' => 'Ruben']);
});

it('cannot have conditional lazy data manually loaded', function () {
    $blueprint = new class () extends Data {
        public function __construct(
            public string|Lazy|null $name = null
        ) {
        }

        public static function create(string $name): static
        {
            return new self(
                Lazy::when(fn () => $name === 'Ruben', fn () => $name)
            );
        }
    };

    $data = $blueprint::create('Freek');

    expect($data->include('name')->toArray())->toBeEmpty();
});

it('can include data based upon relations loaded', function () {
    $model = FakeNestedModel::factory()->create();

    $transformed = FakeNestedModelData::createWithLazyWhenLoaded($model)->all();

    $this->assertArrayNotHasKey('fake_model', $transformed);

    $transformed = FakeNestedModelData::createWithLazyWhenLoaded($model->load('fakeModel'))->all();

    $this->assertArrayHasKey('fake_model', $transformed);
    expect($transformed['fake_model'])->toBeInstanceOf(FakeModelData::class);
});

it('can include data based upon relations loaded when they are null', function () {
    $model = FakeNestedModel::factory(['fake_model_id' => null])->create();

    $transformed = FakeNestedModelData::createWithLazyWhenLoaded($model)->all();

    $this->assertArrayNotHasKey('fake_model', $transformed);

    $transformed = FakeNestedModelData::createWithLazyWhenLoaded($model->load('fakeModel'))->all();

    $this->assertArrayHasKey('fake_model', $transformed);
    expect($transformed['fake_model'])->toBeNull();
});

it('can have default included lazy data', function () {
    $data = new class ('Freek') extends Data {
        public function __construct(public string|Lazy $name)
        {
        }
    };

    expect($data->toArray())->toEqual(['name' => 'Freek']);
});

it('can exclude default lazy data', function () {
    $data = DefaultLazyData::from('Freek');

    expect($data->exclude('name')->toArray())->toEqual([]);
});

it('can get the empty version of a data object', function () {
    $this->assertEquals([
        'property' => null,
        'lazyProperty' => null,
        'array' => [],
        'collection' => [],
        'dataCollection' => [],
        'data' => [
            'string' => null,
        ],
        'lazyData' => [
            'string' => null,
        ],
        'defaultProperty' => true,
    ], EmptyData::empty());
});

it('can overwrite properties in an empty version of a data object', function () {
    $this->assertEquals([
        'string' => null,
    ], SimpleData::empty());

    $this->assertEquals([
        'string' => 'Ruben',
    ], SimpleData::empty(['string' => 'Ruben']));
});

it('will use transformers to convert specific types', function () {
    $date = new DateTime('16 may 1994');

    $data = new class ($date) extends Data {
        public function __construct(public DateTime $date)
        {
        }
    };

    expect($data->toArray())->toEqual(['date' => '1994-05-16T00:00:00+00:00']);
});

it('can manually specify a transformer', function () {
    $date = new DateTime('16 may 1994');

    $data = new class ($date) extends Data {
        public function __construct(
            #[WithTransformer(DateTimeInterfaceTransformer::class, 'd-m-Y')]
            public $date
        ) {
        }
    };

    expect($data->toArray())->toEqual(['date' => '16-05-1994']);
});

test('a transformer will never handle a null value', function () {
    $data = new class (null) extends Data {
        public function __construct(
            #[WithTransformer(DateTimeInterfaceTransformer::class, 'd-m-Y')]
            public $date
        ) {
        }
    };

    expect($data->toArray())->toEqual(['date' => null]);
});

it('can dynamically include data based upon the request', function () {
    $response = LazyData::from('Ruben')->toResponse(request());

    $includedResponse = LazyData::from('Ruben')->toResponse(request()->merge([
        'include' => 'name',
    ]));

    expect($response->getData(true))->toEqual([]);

    expect($includedResponse->getData(true))->toEqual(['name' => 'Ruben']);
});

it('can disable including data dynamically from the request', function () {
    LazyData::$allowedIncludes = [];

    $response = LazyData::from('Ruben')->toResponse(request()->merge([
        'include' => 'name',
    ]));

    expect($response->getData(true))->toEqual([]);

    LazyData::$allowedIncludes = ['name'];

    $response = LazyData::from('Ruben')->toResponse(request()->merge([
        'include' => 'name',
    ]));

    expect($response->getData(true))->toEqual(['name' => 'Ruben']);

    LazyData::$allowedIncludes = null;

    $response = LazyData::from('Ruben')->toResponse(request()->merge([
        'include' => 'name',
    ]));

    expect($response->getData(true))->toEqual(['name' => 'Ruben']);
});

it('can dynamically exclude data based upon the request', function () {
    $response = DefaultLazyData::from('Ruben')->toResponse(request());

    $excludedResponse = DefaultLazyData::from('Ruben')->toResponse(request()->merge([
        'exclude' => 'name',
    ]));

    expect($response->getData(true))->toEqual(['name' => 'Ruben']);

    expect($excludedResponse->getData(true))->toEqual([]);
});

it('can disable excluding data dynamically from the request', function () {
    DefaultLazyData::$allowedExcludes = [];

    $response = DefaultLazyData::from('Ruben')->toResponse(request()->merge([
        'exclude' => 'name',
    ]));

    expect($response->getData(true))->toEqual(['name' => 'Ruben']);

    DefaultLazyData::$allowedExcludes = ['name'];

    $response = DefaultLazyData::from('Ruben')->toResponse(request()->merge([
        'exclude' => 'name',
    ]));

    expect($response->getData(true))->toEqual([]);

    DefaultLazyData::$allowedExcludes = null;

    $response = DefaultLazyData::from('Ruben')->toResponse(request()->merge([
        'exclude' => 'name',
    ]));

    expect($response->getData(true))->toEqual([]);
});

it('can get the data object without transforming', function () {
    $data = new class ($dataObject = new SimpleData('Test'), $dataCollection = SimpleData::collection([new SimpleData('A'), new SimpleData('B'), ]), Lazy::create(fn () => new SimpleData('Lazy')), 'Test', $transformable = new DateTime('16 may 1994'), ) extends Data {
        public function __construct(
            public SimpleData $data,
            public DataCollection $dataCollection,
            public Lazy|Data $lazy,
            public string $string,
            public DateTime $transformable
        ) {
        }
    };

    $this->assertEquals([
        'data' => $dataObject,
        'dataCollection' => $dataCollection,
        'string' => 'Test',
        'transformable' => $transformable,
    ], $data->all());

    $this->assertEquals([
        'data' => $dataObject,
        'dataCollection' => $dataCollection,
        'lazy' => (new SimpleData('Lazy'))->withPartialsTrees([], []),
        'string' => 'Test',
        'transformable' => $transformable,
    ], $data->include('lazy')->all());
});

it('can append data via method overwrite', function () {
    $data = new class ('Freek') extends Data {
        public function __construct(public string $name)
        {
        }

        public function with(): array
        {
            return ['alt_name' => "{$this->name} from Spatie"];
        }
    };

    $this->assertEquals([
        'name' => 'Freek',
        'alt_name' => 'Freek from Spatie',
    ], $data->toArray());
});

it('can append data via method call', function () {
    $data = new class ('Freek') extends Data {
        public function __construct(public string $name)
        {
        }
    };

    $transformed = $data->additional([
        'company' => 'Spatie',
        'alt_name' => fn (Data $data) => "{$data->name} from Spatie",
    ])->toArray();

    $this->assertEquals([
        'name' => 'Freek',
        'company' => 'Spatie',
        'alt_name' => 'Freek from Spatie',
    ], $transformed);
});

it('can optionally create data', function () {
    /** @var class-string<\Spatie\LaravelData\Data> $dataClass */
    $dataClass = DataBlueprintFactory::new()
        ->withProperty(DataPropertyBlueprintFactory::new('string')->withType('string'))
        ->create();

    expect($dataClass::optional(null))->toBeNull();
    $this->assertEquals(
        new $dataClass('Hello world'),
        $dataClass::optional(['string' => 'Hello world'])
    );
});

it('can validate if an array fits a data object and will throw an exception', function () {
    $dataClass = DataBlueprintFactory::new()
        ->withProperty(DataPropertyBlueprintFactory::new('string')->withType('string'))
        ->create();

    try {
        $dataClass::validate(['string' => 10]);
    } catch (ValidationException $exception) {
        $this->assertEquals([
            'string' => ['The string must be a string.'],
        ], $exception->errors());

        return;
    }

    $this->assertFalse(true, 'We should not end up here');
});

it('can validate if an array fits a data object and returns the data object', function () {
    $dataClass = DataBlueprintFactory::new()
        ->withProperty(DataPropertyBlueprintFactory::new('string')->withType('string'))
        ->create();

    $data = $dataClass::validate(['string' => 'Hello World']);

    expect($data->string)->toEqual('Hello World');
});

it('can create a data model without constructor', function () {
    $this->assertEquals(
        SimpleDataWithoutConstructor::fromString('Hello'),
        SimpleDataWithoutConstructor::from('Hello')
    );

    $this->assertEquals(
        SimpleDataWithoutConstructor::fromString('Hello'),
        SimpleDataWithoutConstructor::from([
            'string' => 'Hello',
        ])
    );

    $this->assertEquals(
        new DataCollection(SimpleDataWithoutConstructor::class, [
            SimpleDataWithoutConstructor::fromString('Hello'),
            SimpleDataWithoutConstructor::fromString('World'),
        ]),
        SimpleDataWithoutConstructor::collection(['Hello', 'World'])
    );
});

it('can create a data object from a model', function () {
    DummyModel::migrate();

    $model = DummyModel::create([
        'string' => 'test',
        'boolean' => true,
        'date' => CarbonImmutable::create(2020, 05, 16, 12, 00, 00),
        'nullable_date' => null,
    ]);

    $dataClass = new class () extends Data {
        public string $string;

        public bool $boolean;

        public Carbon $date;

        public ?Carbon $nullable_date;
    };

    $data = $dataClass::from(DummyModel::findOrFail($model->id));

    expect($data->string)->toEqual('test');
    expect($data->boolean)->toBeTrue();
    expect(CarbonImmutable::create(2020, 05, 16, 12, 00, 00)->eq($data->date))->toBeTrue();
    expect($data->nullable_date)->toBeNull();
});

it('can create a data object from a std class object', function () {
    $object = (object) [
        'string' => 'test',
        'boolean' => true,
        'date' => CarbonImmutable::create(2020, 05, 16, 12, 00, 00),
        'nullable_date' => null,
    ];

    $dataClass = new class () extends Data {
        public string $string;

        public bool $boolean;

        public CarbonImmutable $date;

        public ?Carbon $nullable_date;
    };

    $data = $dataClass::from($object);

    expect($data->string)->toEqual('test');
    expect($data->boolean)->toBeTrue();
    expect(CarbonImmutable::create(2020, 05, 16, 12, 00, 00)->eq($data->date))->toBeTrue();
    expect($data->nullable_date)->toBeNull();
});

it('can add the with data trait to a request', function () {
    $formRequest = new class () extends FormRequest {
        use WithData;

        public string $dataClass = SimpleData::class;
    };

    $formRequest->replace([
        'string' => 'Hello World',
    ]);

    $data = $formRequest->getData();

    expect($data)->toEqual(SimpleData::from('Hello World'));
});

it('can add the with data trait to a model', function () {
    $model = new class () extends Model {
        use WithData;

        protected string $dataClass = SimpleData::class;
    };

    $model->fill([
        'string' => 'Hello World',
    ]);

    $data = $model->getData();

    expect($data)->toEqual(SimpleData::from('Hello World'));
});

it('can define the with data trait data class by method', function () {
    $arrayable = new class () implements Arrayable {
        use WithData;

        public function toArray()
        {
            return [
                'string' => 'Hello World',
            ];
        }

        protected function dataClass(): string
        {
            return SimpleData::class;
        }
    };

    $data = $arrayable->getData();

    expect($data)->toEqual(SimpleData::from('Hello World'));
});

it('always validates requests when passed to the from method', function () {
    RequestData::clear();

    try {
        RequestData::from(new Request());
    } catch (ValidationException $exception) {
        $this->assertEquals([
            'string' => [__('validation.required', ['attribute' => 'string'])],
        ], $exception->errors());

        return;
    }

    $this->fail('We should not end up here');
});

it('has support for readonly properties', function () {
    $this->onlyPHP81();

    $data = ReadonlyData::from(['string' => 'Hello world']);

    expect($data)->toBeInstanceOf(ReadonlyData::class);
    expect($data->string)->toEqual('Hello world');
});

it('has support for intersection types', function () {
    $this->onlyPHP81();

    $collection = collect(['a', 'b', 'c']);

    $data = IntersectionTypeData::from(['intersection' => $collection]);

    expect($data)->toBeInstanceOf(IntersectionTypeData::class);
    expect($data->intersection)->toEqual($collection);
});

it('can transform to json', function () {
    expect(SimpleData::from('Hello')->toJson())->toEqual('{"string":"Hello"}');
    expect(json_encode(SimpleData::from('Hello')))->toEqual('{"string":"Hello"}');
});

it('can construct a data object with both constructor promoted and default properties', function () {
    $dataClass = new class ('') extends Data {
        public string $property;

        public function __construct(
            public string $promoted_property,
        ) {
        }
    };

    $data = $dataClass::from([
        'property' => 'A',
        'promoted_property' => 'B',
    ]);

    expect($data->property)->toEqual('A');
    expect($data->promoted_property)->toEqual('B');
});

it('can construct a data object with default values', function () {
    $data = DataWithDefaults::from([
        'property' => 'Test',
        'promoted_property' => 'Test Again',
    ]);

    expect($data->property)->toEqual('Test');
    expect($data->promoted_property)->toEqual('Test Again');
    expect($data->default_property)->toEqual('Hello');
    expect($data->default_promoted_property)->toEqual('Hello Again');
});

it('can construct a data object with default values and overwrite them', function () {
    $data = DataWithDefaults::from([
        'property' => 'Test',
        'default_property' => 'Test',
        'promoted_property' => 'Test Again',
        'default_promoted_property' => 'Test Again',
    ]);

    expect($data->property)->toEqual('Test');
    expect($data->promoted_property)->toEqual('Test Again');
    expect($data->default_property)->toEqual('Test');
    expect($data->default_promoted_property)->toEqual('Test Again');
});

it('can use a custom transformer to transform data objects and collections', function () {
    $nestedData = new class (42, 'Hello World') extends Data {
        public function __construct(
            public int $integer,
            public string $string,
        ) {
        }
    };

    $nestedDataCollection = $nestedData::collection([
        ['integer' => 314, 'string' => 'pi'],
        ['integer' => '69', 'string' => 'Laravel after hours'],
    ]);

    $dataWithDefaultTransformers = new class ($nestedData, $nestedDataCollection) extends Data {
        public function __construct(
            public Data $nestedData,
            public DataCollection $nestedDataCollection,
        ) {
        }
    };

    $dataWithSpecificTransformers = new class ($nestedData, $nestedDataCollection) extends Data {
        public function __construct(
            #[WithTransformer(ConfidentialDataTransformer::class)]
            public Data $nestedData,
            #[WithTransformer(ConfidentialDataCollectionTransformer::class)]
            public DataCollection $nestedDataCollection,
        ) {
        }
    };

    $this->assertEquals([
        'nestedData' => ['integer' => 42, 'string' => 'Hello World'],
        'nestedDataCollection' => [
            ['integer' => 314, 'string' => 'pi'],
            ['integer' => '69', 'string' => 'Laravel after hours'],
        ],
    ], $dataWithDefaultTransformers->toArray());

    $this->assertEquals([
        'nestedData' => ['integer' => 'CONFIDENTIAL', 'string' => 'CONFIDENTIAL'],
        'nestedDataCollection' => [
            ['integer' => 'CONFIDENTIAL', 'string' => 'CONFIDENTIAL'],
            ['integer' => 'CONFIDENTIAL', 'string' => 'CONFIDENTIAL'],
        ],
    ], $dataWithSpecificTransformers->toArray());
});

it('can transform built in types with custom transformers', function () {
    $data = new class ('Hello World', 'Hello World') extends Data {
        public function __construct(
            public string $without_transformer,
            #[WithTransformer(StringToUpperTransformer::class)]
            public string $with_transformer
        ) {
        }
    };
    $this->assertEquals([
        'without_transformer' => 'Hello World',
        'with_transformer' => 'HELLO WORLD',
    ], $data->toArray());
});

it('can cast data objects and collections using a custom cast', function () {
    $dataWithDefaultCastsClass = new class (new SimpleData(''), SimpleData::collection([])) extends Data {
        public function __construct(
            public SimpleData $nestedData,
            #[DataCollectionOf(SimpleData::class)]
            public DataCollection $nestedDataCollection,
        ) {
        }
    };

    $dataWithCustomCastsClass = new class (new SimpleData(''), SimpleData::collection([])) extends Data {
        public function __construct(
            #[WithCast(ConfidentialDataCast::class)]
            public SimpleData $nestedData,
            #[WithCast(ConfidentialDataCollectionCast::class)]
            #[DataCollectionOf(SimpleData::class)]
            public DataCollection $nestedDataCollection,
        ) {
        }
    };

    $dataWithDefaultCasts = $dataWithDefaultCastsClass::from([
        'nestedData' => 'a secret',
        'nestedDataCollection' => ['another secret', 'yet another secret'],
    ]);

    $dataWithCustomCasts = $dataWithCustomCastsClass::from([
        'nestedData' => 'a secret',
        'nestedDataCollection' => ['another secret', 'yet another secret'],
    ]);

    expect($dataWithDefaultCasts->nestedData)->toEqual(SimpleData::from('a secret'));
    expect($dataWithDefaultCasts->nestedDataCollection)->toEqual(SimpleData::collection(['another secret', 'yet another secret']));

    expect($dataWithCustomCasts->nestedData)->toEqual(SimpleData::from('CONFIDENTIAL'));
    expect($dataWithCustomCasts->nestedDataCollection)->toEqual(SimpleData::collection(['CONFIDENTIAL', 'CONFIDENTIAL']));
});

it('can cast built in types with custom casts', function () {
    $dataClass = new class ('', '') extends Data {
        public function __construct(
            public string $without_cast,
            #[WithCast(StringToUpperCast::class)]
            public string $with_cast
        ) {
        }
    };

    $data = $dataClass::from([
        'without_cast' => 'Hello World',
        'with_cast' => 'Hello World',
    ]);

    expect($data->without_cast)->toEqual('Hello World');
    expect($data->with_cast)->toEqual('HELLO WORLD');
});

it('continues value assignment after a false boolean', function () {
    $dataClass = new class () extends Data {
        public bool $false;

        public bool $true;

        public string $string;

        public Carbon $date;
    };

    $data = $dataClass::from([
        'false' => false,
        'true' => true,
        'string' => 'string',
        'date' => Carbon::create(2020, 05, 16, 12, 00, 00),
    ]);

    expect($data->false)->toBeFalse();
    expect($data->true)->toBeTrue();
    expect($data->string)->toEqual('string');
    expect(Carbon::create(2020, 05, 16, 12, 00, 00)->equalTo($data->date))->toBeTrue();
});
