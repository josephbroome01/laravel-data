<?php

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Lazy;
use Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer;
use Spatie\LaravelData\Tests\Fakes\SimpleData;
use Spatie\LaravelData\Tests\TestCase;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;


it('can covert a data object to typescript', function () {
    $config = TypeScriptTransformerConfig::create();

    $data = new class (null, 42, true, 'Hello world', 3.14, ['the', 'meaning', 'of', 'life'], Lazy::create(fn () => 'Lazy'), SimpleData::from('Simple data'), SimpleData::collection([]), SimpleData::collection([]), SimpleData::collection([])) extends Data {
        public function __construct(
            public null|int $nullable,
            public int $int,
            public bool $bool,
            public string $string,
            public float $float,
            /** @var string[] */
            public array $array,
            public Lazy|string $lazy,
            public SimpleData $simpleData,
            /** @var \Spatie\LaravelData\Tests\Fakes\SimpleData[] */
            public DataCollection $dataCollection,
            /** @var DataCollection<\Spatie\LaravelData\Tests\Fakes\SimpleData> */
            public DataCollection $dataCollectionAlternative,
            #[DataCollectionOf(SimpleData::class)]
            public DataCollection $dataCollectionWithAttribute,
        ) {
        }
    };

    $transformer = new DataTypeScriptTransformer($config);

    $reflection = new ReflectionClass($data);

    expect($transformer->canTransform($reflection))->toBeTrue();
    $this->assertMatchesSnapshot($transformer->transform($reflection, 'DataObject')->transformed);
});
