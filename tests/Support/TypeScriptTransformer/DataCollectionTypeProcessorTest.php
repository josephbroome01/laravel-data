<?php

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer;
use Spatie\LaravelData\Tests\Fakes\SimpleData;
use Spatie\LaravelData\Tests\TestCase;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;


it('uses the correct types for data collection of attributes', function () {
    $config = TypeScriptTransformerConfig::create();

    $data = new class (SimpleData::collection([]), SimpleData::collection([]), SimpleData::collection([])) extends Data {
        public function __construct(
            #[DataCollectionOf(SimpleData::class)]
            public DataCollection $dataCollection,
            #[DataCollectionOf(SimpleData::class)]
            public ?DataCollection $dataCollectionWithNull,
            #[DataCollectionOf(SimpleData::class)]
            public DataCollection|null $dataCollectionWithNullable,
        ) {
        }
    };

    $transformer = new DataTypeScriptTransformer($config);

    $reflection = new ReflectionClass($data);

    expect($transformer->canTransform($reflection))->toBeTrue();
    $this->assertMatchesSnapshot($transformer->transform($reflection, 'DataObject')->transformed);
});
