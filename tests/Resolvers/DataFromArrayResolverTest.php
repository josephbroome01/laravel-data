<?php

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Lazy;
use Spatie\LaravelData\Resolvers\DataFromArrayResolver;
use Spatie\LaravelData\Tests\Fakes\BuiltInTypeWithCastData;
use Spatie\LaravelData\Tests\Fakes\ComplicatedData;
use Spatie\LaravelData\Tests\Fakes\DateCastData;
use Spatie\LaravelData\Tests\Fakes\DummyBackedEnum;
use Spatie\LaravelData\Tests\Fakes\DummyModel;
use Spatie\LaravelData\Tests\Fakes\EnumCastData;
use Spatie\LaravelData\Tests\Fakes\ModelData;
use Spatie\LaravelData\Tests\Fakes\NestedLazyData;
use Spatie\LaravelData\Tests\Fakes\NestedModelCollectionData;
use Spatie\LaravelData\Tests\Fakes\NestedModelData;
use Spatie\LaravelData\Tests\Fakes\SimpleData;

beforeEach(function () {
    $this->action = app(DataFromArrayResolver::class);
});

it('maps default types', function () {
    /** @var \Spatie\LaravelData\Tests\Fakes\ComplicatedData $data */
    $data = $this->action->execute(
        ComplicatedData::class,
        [
            'withoutType' => 42,
            'int' => 42,
            'bool' => true,
            'float' => 3.14,
            'string' => 'Hello world',
            'array' => [1, 1, 2, 3, 5, 8],
            'nullable' => null,
            'mixed' => 42,
            'explicitCast' => '16-06-1994',
            'defaultCast' => '1994-05-16T12:00:00+01:00',
            'nestedData' => [
                'string' => 'hello',
            ],
            'nestedCollection' => [
                ['string' => 'never'],
                ['string' => 'gonna'],
                ['string' => 'give'],
                ['string' => 'you'],
                ['string' => 'up'],
            ],
        ]
    );

    expect($data)->toBeInstanceOf(ComplicatedData::class);
    expect($data->withoutType)->toEqual(42);
    expect($data->int)->toEqual(42);
    expect($data->bool)->toBeTrue();
    expect($data->float)->toEqual(3.14);
    expect($data->string)->toEqual('Hello world');
    expect($data->array)->toEqual([1, 1, 2, 3, 5, 8]);
    expect($data->nullable)->toBeNull();
    expect($data->mixed)->toEqual(42);
    expect($data->defaultCast)->toEqual(DateTime::createFromFormat(DATE_ATOM, '1994-05-16T12:00:00+01:00'));
    expect($data->explicitCast)->toEqual(CarbonImmutable::createFromFormat('d-m-Y', '16-06-1994'));
    expect($data->nestedData)->toEqual(SimpleData::from('hello'));
    $this->assertEquals(SimpleData::collection([
        SimpleData::from('never'),
        SimpleData::from('gonna'),
        SimpleData::from('give'),
        SimpleData::from('you'),
        SimpleData::from('up'),
    ]), $data->nestedCollection);
});

it('wont cast a property that is already in the correct type', function () {
    /** @var \Spatie\LaravelData\Tests\Fakes\ComplicatedData $data */
    $data = $this->action->execute(
        ComplicatedData::class,
        [
            'withoutType' => 42,
            'int' => 42,
            'bool' => true,
            'float' => 3.14,
            'string' => 'Hello world',
            'array' => [1, 1, 2, 3, 5, 8],
            'nullable' => null,
            'mixed' => 42,
            'explicitCast' => DateTime::createFromFormat('d-m-Y', '16-06-1994'),
            'defaultCast' => DateTime::createFromFormat(DATE_ATOM, '1994-05-16T12:00:00+02:00'),
            'nestedData' => SimpleData::from('hello'),
            'nestedCollection' => SimpleData::collection([
                'never', 'gonna', 'give', 'you', 'up',
            ]),
        ]
    );

    expect($data)->toBeInstanceOf(ComplicatedData::class);
    expect($data->withoutType)->toEqual(42);
    expect($data->int)->toEqual(42);
    expect($data->bool)->toBeTrue();
    expect($data->float)->toEqual(3.14);
    expect($data->string)->toEqual('Hello world');
    expect($data->array)->toEqual([1, 1, 2, 3, 5, 8]);
    expect($data->nullable)->toBeNull();
    expect($data->mixed)->toEqual(42);
    expect($data->defaultCast)->toEqual(DateTime::createFromFormat(DATE_ATOM, '1994-05-16T12:00:00+02:00'));
    expect($data->explicitCast)->toEqual(DateTime::createFromFormat('d-m-Y', '16-06-1994'));
    expect($data->nestedData)->toEqual(SimpleData::from('hello'));
    $this->assertEquals(SimpleData::collection([
        SimpleData::from('never'),
        SimpleData::from('gonna'),
        SimpleData::from('give'),
        SimpleData::from('you'),
        SimpleData::from('up'),
    ]), $data->nestedCollection);
});

it('will allow a nested data object to handle their own types', function () {
    $model = new DummyModel(['id' => 10]);

    /** @var \Spatie\LaravelData\Tests\Fakes\NestedModelData $data */
    $withoutModelData = $this->action->execute(
        NestedModelData::class,
        [
            'model' => ['id' => 10],
        ]
    );

    expect($withoutModelData)->toBeInstanceOf(NestedModelData::class);
    expect($withoutModelData->model->id)->toEqual(10);

    /** @var \Spatie\LaravelData\Tests\Fakes\NestedModelData $data */
    $withModelData = $this->action->execute(
        NestedModelData::class,
        [
            'model' => $model,
        ]
    );

    expect($withModelData)->toBeInstanceOf(NestedModelData::class);
    expect($withModelData->model->id)->toEqual(10);
});

it('will allow a nested collection object to handle its own types', function () {
    /** @var \Spatie\LaravelData\Tests\Fakes\NestedModelCollectionData $data */
    $data = $this->action->execute(
        NestedModelCollectionData::class,
        [
            'models' => [['id' => 10], ['id' => 20],],
        ]
    );

    expect($data)->toBeInstanceOf(NestedModelCollectionData::class);
    $this->assertEquals(
        ModelData::collection([['id' => 10], ['id' => 20]]),
        $data->models
    );

    /** @var \Spatie\LaravelData\Tests\Fakes\NestedModelCollectionData $data */
    $data = $this->action->execute(
        NestedModelCollectionData::class,
        [
            'models' => [new DummyModel(['id' => 10]), new DummyModel(['id' => 20]),],
        ]
    );

    expect($data)->toBeInstanceOf(NestedModelCollectionData::class);
    $this->assertEquals(
        ModelData::collection([['id' => 10], ['id' => 20]]),
        $data->models
    );

    /** @var \Spatie\LaravelData\Tests\Fakes\NestedModelCollectionData $data */
    $data = $this->action->execute(
        NestedModelCollectionData::class,
        [
            'models' => ModelData::collection([['id' => 10], ['id' => 20]]),
        ]
    );

    expect($data)->toBeInstanceOf(NestedModelCollectionData::class);
    $this->assertEquals(
        ModelData::collection([['id' => 10], ['id' => 20]]),
        $data->models
    );
});

it('works nicely with lazy data', function () {
    /** @var \Spatie\LaravelData\Tests\Fakes\NestedLazyData $data */
    $data = $this->action->execute(
        NestedLazyData::class,
        ['simple' => Lazy::create(fn () => SimpleData::from('Hello'))]
    );

    expect($data->simple)->toBeInstanceOf(Lazy::class);
    expect($data->simple)->toEqual(Lazy::create(fn () => SimpleData::from('Hello')));
});

it('allows casting of built in types', function () {
    /** @var \Spatie\LaravelData\Tests\Fakes\BuiltInTypeWithCastData $data */
    $data = $this->action->execute(
        BuiltInTypeWithCastData::class,
        ['money' => 3.14]
    );

    expect($data->money)->toBeInt();
    expect($data->money)->toEqual(314);
});

it('allows casting', function () {
    $data = $this->action->execute(
        DateCastData::class,
        ['date' => '2022-01-18']
    );

    expect($data->date)->toBeInstanceOf(DateTimeImmutable::class);
    expect($data->date)->toEqual(DateTimeImmutable::createFromFormat('Y-m-d', '2022-01-18'));
});

it('allows casting of enums', function () {
    $this->onlyPHP81();

    $data = $this->action->execute(
        EnumCastData::class,
        ['enum' => 'foo']
    );

    expect($data->enum)->toBeInstanceOf(DummyBackedEnum::class);
    expect($data->enum)->toEqual(DummyBackedEnum::FOO);
});
