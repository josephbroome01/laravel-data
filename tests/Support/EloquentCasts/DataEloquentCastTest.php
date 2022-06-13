<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Tests\Fakes\DummyModelWithCasts;
use Spatie\LaravelData\Tests\Fakes\SimpleData;
use Spatie\LaravelData\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    DummyModelWithCasts::migrate();
});

it('can save a data object', function () {
    DummyModelWithCasts::create([
        'data' => new SimpleData('Test'),
    ]);

    $this->assertDatabaseHas(DummyModelWithCasts::class, [
        'data' => json_encode(['string' => 'Test']),
    ]);
});

it('can save a data object as an array', function () {
    DummyModelWithCasts::create([
        'data' => ['string' => 'Test'],
    ]);

    $this->assertDatabaseHas(DummyModelWithCasts::class, [
        'data' => json_encode(['string' => 'Test']),
    ]);
});

it('can load a data object', function () {
    DB::table('dummy_model_with_casts')->insert([
        'data' => json_encode(['string' => 'Test']),
    ]);

    /** @var \Spatie\LaravelData\Tests\Fakes\DummyModelWithCasts $model */
    $model = DummyModelWithCasts::first();

    $this->assertEquals(
        new SimpleData('Test'),
        $model->data
    );
});

it('can save a null as a value', function () {
    DummyModelWithCasts::create([
        'data' => null,
    ]);

    $this->assertDatabaseHas(DummyModelWithCasts::class, [
        'data' => null,
    ]);
});

it('can load null as a value', function () {
    DB::table('dummy_model_with_casts')->insert([
        'data' => null,
    ]);

    /** @var \Spatie\LaravelData\Tests\Fakes\DummyModelWithCasts $model */
    $model = DummyModelWithCasts::first();

    $this->assertNull($model->data);
});
