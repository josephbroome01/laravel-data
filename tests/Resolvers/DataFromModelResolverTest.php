<?php

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Resolvers\DataFromModelResolver;
use Spatie\LaravelData\Tests\Factories\DataBlueprintFactory;
use Spatie\LaravelData\Tests\Factories\DataPropertyBlueprintFactory;
use Spatie\LaravelData\Tests\Fakes\FakeModelData;
use Spatie\LaravelData\Tests\Fakes\Models\FakeModel;
use Spatie\LaravelData\Tests\Fakes\Models\FakeNestedModel;

beforeEach(function () {
    $this->resolver = app(DataFromModelResolver::class);
});

it('can get a data object from model', function () {
    $model = FakeModel::factory()->create();

    $data = FakeModelData::from($model);

    expect($data->string)->toEqual($model->string);
    expect($data->nullable)->toEqual($model->nullable);
    expect($data->date)->toEqual($model->date);
});

it('can get a data object with nesting from model and relations', function () {
    $model = FakeModel::factory()->create();

    $nestedModelA = FakeNestedModel::factory()->for($model)->create();
    $nestedModelB = FakeNestedModel::factory()->for($model)->create();

    $data = FakeModelData::from($model->load('fakeNestedModels'));

    expect($data->string)->toEqual($model->string);
    expect($data->nullable)->toEqual($model->nullable);
    expect($data->date)->toEqual($model->date);

    expect($data->fake_nested_models)->toHaveCount(2);

    expect($data->fake_nested_models[0]->string)->toEqual($nestedModelA->string);
    expect($data->fake_nested_models[0]->nullable)->toEqual($nestedModelA->nullable);
    expect($data->fake_nested_models[0]->date)->toEqual($nestedModelA->date);

    expect($data->fake_nested_models[1]->string)->toEqual($nestedModelB->string);
    expect($data->fake_nested_models[1]->nullable)->toEqual($nestedModelB->nullable);
    expect($data->fake_nested_models[1]->date)->toEqual($nestedModelB->date);
});

it('can get a data object from model with dates', function () {
    $fakeModelClass = new class () extends Model {
        protected $casts = [
            'date' => 'date',
            'datetime' => 'datetime',
            'immutable_date' => 'immutable_date',
            'immutable_datetime' => 'immutable_datetime',
        ];
    };

    $model = $fakeModelClass::make([
        'date' => Carbon::create(2020, 05, 16, 12, 00, 00),
        'datetime' => Carbon::create(2020, 05, 16, 12, 00, 00),
        'immutable_date' => Carbon::create(2020, 05, 16, 12, 00, 00),
        'immutable_datetime' => Carbon::create(2020, 05, 16, 12, 00, 00),
        'created_at' => Carbon::create(2020, 05, 16, 12, 00, 00),
        'updated_at' => Carbon::create(2020, 05, 16, 12, 00, 00),
    ]);

    $dataClass = DataBlueprintFactory::new('DataFromModelWithDates')
        ->withProperty(
            DataPropertyBlueprintFactory::new('date')->withType(Carbon::class),
            DataPropertyBlueprintFactory::new('datetime')->withType(Carbon::class),
            DataPropertyBlueprintFactory::new('immutable_date')->withType(CarbonImmutable::class),
            DataPropertyBlueprintFactory::new('immutable_datetime')->withType(CarbonImmutable::class),
            DataPropertyBlueprintFactory::new('created_at')->withType(Carbon::class),
            DataPropertyBlueprintFactory::new('updated_at')->withType(Carbon::class),
        )
        ->create();

    $data = $this->resolver->execute($dataClass, $model);

    expect($data->date->eq(Carbon::create(2020, 05, 16, 00, 00, 00)))->toBeTrue();
    expect($data->datetime->eq(Carbon::create(2020, 05, 16, 12, 00, 00)))->toBeTrue();
    expect($data->immutable_date->eq(Carbon::create(2020, 05, 16, 00, 00, 00)))->toBeTrue();
    expect($data->immutable_datetime->eq(Carbon::create(2020, 05, 16, 12, 00, 00)))->toBeTrue();
    expect($data->created_at->eq(Carbon::create(2020, 05, 16, 12, 00, 00)))->toBeTrue();
    expect($data->updated_at->eq(Carbon::create(2020, 05, 16, 12, 00, 00)))->toBeTrue();
});
