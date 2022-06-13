<?php

use Closure;
use Generator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Spatie\LaravelData\Tests\Fakes\DefaultLazyData;
use Spatie\LaravelData\Tests\Fakes\LazyData;
use Spatie\LaravelData\Tests\Fakes\SimpleData;

uses(TestCase::class);

it('can get a paginated data collection', function () {
    $items = Collection::times(100, fn (int $index) => "Item {$index}");

    $paginator = new LengthAwarePaginator(
        $items->forPage(1, 15),
        100,
        15
    );

    $this->assertMatchesJsonSnapshot(SimpleData::collection($paginator)->toArray());
});

test('a collection can be constructed with data objects', function () {
    $collectionA = SimpleData::collection([
        SimpleData::from('A'),
        SimpleData::from('B'),
    ]);

    $collectionB = SimpleData::collection([
        'A',
        'B',
    ]);

    $this->assertEquals($collectionB->toArray(), $collectionA->toArray());
});

test('a collection can be filtered', function () {
    $collection = SimpleData::collection(['A', 'B']);

    $filtered = $collection->filter(fn (SimpleData $data) => $data->string === 'A')->toArray();

    $this->assertEquals([
        ['string' => 'A'],
    ], $filtered);
});

test('a paginated collection cannot be filtered', function () {
    $collection = SimpleData::collection(
        new LengthAwarePaginator(['A', 'B'], 2, 15)
    );

    $filtered = $collection->filter(fn (SimpleData $data) => $data->string === 'A')->toArray();

    $this->assertEquals([
        ['string' => 'A'],
        ['string' => 'B'],
    ], $filtered['data']);
});

test('a collection can be transformed', function () {
    $collection = SimpleData::collection(['A', 'B']);

    $filtered = $collection->through(fn (SimpleData $data) => new SimpleData("{$data->string}x"))->toArray();

    $this->assertEquals([
        ['string' => 'Ax'],
        ['string' => 'Bx'],
    ], $filtered);
});

test('a paginated collection can be transformed', function () {
    $collection = SimpleData::collection(
        new LengthAwarePaginator(['A', 'B'], 2, 15)
    );

    $filtered = $collection->through(fn (SimpleData $data) => new SimpleData("{$data->string}x"))->toArray();

    $this->assertEquals([
        ['string' => 'Ax'],
        ['string' => 'Bx'],
    ], $filtered['data']);
});

it('is iteratable', function () {
    $collection = SimpleData::collection([
        'A', 'B', 'C', 'D',
    ]);

    $letters = [];

    foreach ($collection as $item) {
        $letters[] = $item->string;
    }

    $this->assertEquals(['A', 'B', 'C', 'D'], $letters);
});

it('has array access', function (Closure $collection) {
    $collection = $collection();

    // Count
    $this->assertEquals(4, count($collection));

    // Offset exists
    $this->assertFalse(empty($collection[3]));

    $this->assertTrue(empty($collection[5]));

    // Offset get
    $this->assertEquals(SimpleData::from('A'), $collection[0]);

    $this->assertEquals(SimpleData::from('D'), $collection[3]);

    if ($collection->items() instanceof AbstractPaginator || $collection->items() instanceof CursorPaginator) {
        return;
    }

    // Offset set
    $collection[2] = 'And now something completely different';
    $collection[4] = 'E';

    $this->assertEquals(SimpleData::from('And now something completely different'), $collection[2]);
    $this->assertEquals(SimpleData::from('E'), $collection[4]);

    // Offset unset
    unset($collection[4]);

    $this->assertCount(4, $collection);
})->with('arrayAccessCollections');

it('can dynamically include data based upon the request', function () {
    $response = LazyData::collection(['Ruben', 'Freek', 'Brent'])->toResponse(request());

    $includedResponse = LazyData::collection(['Ruben', 'Freek', 'Brent'])->toResponse(request()->merge([
        'include' => 'name',
    ]));

    $this->assertEquals([
        [],
        [],
        [],
    ], $response->getData(true));

    $this->assertEquals(
        [
            ['name' => 'Ruben'],
            ['name' => 'Freek'],
            ['name' => 'Brent'],
        ],
        $includedResponse->getData(true)
    );
});

it('can disable manually including data in the request', function () {
    LazyData::$allowedIncludes = [];

    $response = LazyData::collection(['Ruben', 'Freek', 'Brent'])->toResponse(request()->merge([
        'include' => 'name',
    ]));

    $this->assertEquals([
        [],
        [],
        [],
    ], $response->getData(true));

    LazyData::$allowedIncludes = ['name'];

    $response = LazyData::collection(['Ruben', 'Freek', 'Brent'])->toResponse(request()->merge([
        'include' => 'name',
    ]));

    $this->assertEquals([
        ['name' => 'Ruben'],
        ['name' => 'Freek'],
        ['name' => 'Brent'],
    ], $response->getData(true));

    LazyData::$allowedIncludes = null;

    $response = LazyData::collection(['Ruben', 'Freek', 'Brent'])->toResponse(request()->merge([
        'include' => 'name',
    ]));

    $this->assertEquals([
        ['name' => 'Ruben'],
        ['name' => 'Freek'],
        ['name' => 'Brent'],
    ], $response->getData(true));
});

it('can dynamically exclude data based upon the request', function () {
    $response = DefaultLazyData::collection(['Ruben', 'Freek', 'Brent'])->toResponse(request());

    $excludedResponse = DefaultLazyData::collection(['Ruben', 'Freek', 'Brent'])->toResponse(request()->merge([
        'exclude' => 'name',
    ]));

    $this->assertEquals(
        [
            ['name' => 'Ruben'],
            ['name' => 'Freek'],
            ['name' => 'Brent'],
        ],
        $response->getData(true)
    );

    $this->assertEquals([
        [],
        [],
        [],
    ], $excludedResponse->getData(true));
});

it('can disable manually excluding data in the request', function () {
    DefaultLazyData::$allowedExcludes = [];

    $response = DefaultLazyData::collection(['Ruben', 'Freek', 'Brent'])->toResponse(request()->merge([
        'exclude' => 'name',
    ]));

    $this->assertEquals([
        ['name' => 'Ruben'],
        ['name' => 'Freek'],
        ['name' => 'Brent'],
    ], $response->getData(true));

    DefaultLazyData::$allowedExcludes = ['name'];

    $response = DefaultLazyData::collection(['Ruben', 'Freek', 'Brent'])->toResponse(request()->merge([
        'exclude' => 'name',
    ]));

    $this->assertEquals([
        [],
        [],
        [],
    ], $response->getData(true));

    DefaultLazyData::$allowedExcludes = null;

    $response = DefaultLazyData::collection(['Ruben', 'Freek', 'Brent'])->toResponse(request()->merge([
        'exclude' => 'name',
    ]));

    $this->assertEquals([
        [],
        [],
        [],
    ], $response->getData(true));
});

it('can update data properties within a collection', function () {
    $collection = LazyData::collection([
        LazyData::from('Never gonna give you up!'),
    ]);

    $this->assertEquals([
        ['name' => 'Never gonna give you up!'],
    ], $collection->include('name')->toArray());

    $collection[0]->name = 'Giving Up on Love';

    $this->assertEquals([
        ['name' => 'Giving Up on Love'],
    ], $collection->include('name')->toArray());

    $collection[] = LazyData::from('Cry for help');

    $this->assertEquals([
        ['name' => 'Giving Up on Love'],
        ['name' => 'Cry for help'],
    ], $collection->include('name')->toArray());

    unset($collection[0]);

    $this->assertEquals([
        1 => ['name' => 'Cry for help'],
    ], $collection->include('name')->toArray());
});

it('supports lazy collections', function () {
    $lazyCollection = new LazyCollection(function () {
        $items = [
            'Never gonna give you up!',
            'Giving Up on Love',
        ];

        foreach ($items as $item) {
            yield $item;
        }
    });

    $collection = SimpleData::collection($lazyCollection);

    $this->assertEquals([
        SimpleData::from('Never gonna give you up!'),
        SimpleData::from('Giving Up on Love'),
    ], $collection->items());

    $transformed = $collection->through(function (SimpleData $data) {
        $data->string = strtoupper($data->string);

        return $data;
    })->filter(fn (SimpleData $data) => $data->string === strtoupper('Never gonna give you up!'))->toArray();

    $this->assertEquals([
        ['string' => strtoupper('Never gonna give you up!')],
    ], $transformed);
});

it('can convert a data collection into a laravel collection', function () {
    $this->assertEquals(
        collect([
            SimpleData::from('A'),
            SimpleData::from('B'),
            SimpleData::from('C'),
        ]),
        SimpleData::collection(['A', 'B', 'C'])->toCollection()
    );
});

test('a collection can be transformed to json', function () {
    $collection = SimpleData::collection(['A', 'B', 'C']);

    $this->assertEquals('[{"string":"A"},{"string":"B"},{"string":"C"}]', $collection->toJson());
    $this->assertEquals('[{"string":"A"},{"string":"B"},{"string":"C"}]', json_encode($collection));
});

it('can reset the keys', function () {
    $collection = SimpleData::collection([
        1 => SimpleData::from('a'),
        3 => SimpleData::from('b'),
    ]);

    $this->assertEquals(
        SimpleData::collection([
            0 => SimpleData::from('a'),
            1 => SimpleData::from('b'),
        ]),
        $collection->values()
    );
});

// Datasets
dataset('arrayAccessCollections', function () {
    yield "array" => [
        fn () => SimpleData::collection([
            'A', 'B', SimpleData::from('C'), SimpleData::from('D'),
        ]),
    ];

    yield "collection" => [
        fn () => SimpleData::collection([
            'A', 'B', SimpleData::from('C'), SimpleData::from('D'),
        ]),
    ];

    yield "paginator" => [
        fn () => SimpleData::collection(new LengthAwarePaginator([
            'A', 'B', SimpleData::from('C'), SimpleData::from('D'),
        ], 4, 15)),
    ];

    yield "cursor paginator" => [
        fn () => SimpleData::collection(new CursorPaginator([
            'A', 'B', SimpleData::from('C'), SimpleData::from('D'),
        ], 4)),
    ];
});
