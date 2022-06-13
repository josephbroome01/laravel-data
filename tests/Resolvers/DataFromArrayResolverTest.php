<?php

use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
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
use Spatie\LaravelData\Tests\TestCase;

uses(TestCase::class);

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

    $this->assertInstanceOf(ComplicatedData::class, $data);
    $this->assertEquals(42, $data->withoutType);
    $this->assertEquals(42, $data->int);
    $this->assertTrue($data->bool);
    $this->assertEquals(3.14, $data->float);
    $this->assertEquals('Hello world', $data->string);
    $this->assertEquals([1, 1, 2, 3, 5, 8], $data->array);
    $this->assertNull($data->nullable);
    $this->assertEquals(42, $data->mixed);
    $this->assertEquals(DateTime::createFromFormat(DATE_ATOM, '1994-05-16T12:00:00+01:00'), $data->defaultCast);
    $this->assertEquals(CarbonImmutable::createFromFormat('d-m-Y', '16-06-1994'), $data->explicitCast);
    $this->assertEquals(SimpleData::from('hello'), $data->nestedData);
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

    $this->assertInstanceOf(ComplicatedData::class, $data);
    $this->assertEquals(42, $data->withoutType);
    $this->assertEquals(42, $data->int);
    $this->assertTrue($data->bool);
    $this->assertEquals(3.14, $data->float);
    $this->assertEquals('Hello world', $data->string);
    $this->assertEquals([1, 1, 2, 3, 5, 8], $data->array);
    $this->assertNull($data->nullable);
    $this->assertEquals(42, $data->mixed);
    $this->assertEquals(DateTime::createFromFormat(DATE_ATOM, '1994-05-16T12:00:00+02:00'), $data->defaultCast);
    $this->assertEquals(DateTime::createFromFormat('d-m-Y', '16-06-1994'), $data->explicitCast);
    $this->assertEquals(SimpleData::from('hello'), $data->nestedData);
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

    $this->assertInstanceOf(NestedModelData::class, $withoutModelData);
    $this->assertEquals(10, $withoutModelData->model->id);

    /** @var \Spatie\LaravelData\Tests\Fakes\NestedModelData $data */
    $withModelData = $this->action->execute(
        NestedModelData::class,
        [
            'model' => $model,
        ]
    );

    $this->assertInstanceOf(NestedModelData::class, $withModelData);
    $this->assertEquals(10, $withModelData->model->id);
});

it('will allow a nested collection object to handle its own types', function () {
    /** @var \Spatie\LaravelData\Tests\Fakes\NestedModelCollectionData $data */
    $data = $this->action->execute(
        NestedModelCollectionData::class,
        [
            'models' => [['id' => 10], ['id' => 20],],
        ]
    );

    $this->assertInstanceOf(NestedModelCollectionData::class, $data);
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

    $this->assertInstanceOf(NestedModelCollectionData::class, $data);
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

    $this->assertInstanceOf(NestedModelCollectionData::class, $data);
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

    $this->assertInstanceOf(Lazy::class, $data->simple);
    $this->assertEquals(Lazy::create(fn () => SimpleData::from('Hello')), $data->simple);
});

it('allows casting of built in types', function () {
    /** @var \Spatie\LaravelData\Tests\Fakes\BuiltInTypeWithCastData $data */
    $data = $this->action->execute(
        BuiltInTypeWithCastData::class,
        ['money' => 3.14]
    );

    $this->assertIsInt($data->money);
    $this->assertEquals(314, $data->money);
});

it('allows casting', function () {
    $data = $this->action->execute(
        DateCastData::class,
        ['date' => '2022-01-18']
    );

    $this->assertInstanceOf(DateTimeImmutable::class, $data->date);
    $this->assertEquals(DateTimeImmutable::createFromFormat('Y-m-d', '2022-01-18'), $data->date);
});

it('allows casting of enums', function () {
    $this->onlyPHP81();

    $data = $this->action->execute(
        EnumCastData::class,
        ['enum' => 'foo']
    );

    $this->assertInstanceOf(DummyBackedEnum::class, $data->enum);
    $this->assertEquals(DummyBackedEnum::FOO, $data->enum);
});
