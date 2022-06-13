it('ca
uses(TestCase::class);
n pass validation', function () {
    performRequest('Hello')
        ->assertOk()
        ->assertJson(['given' => 'Hello']);
});

it('can fail validation', function () {
    performRequest('Hello World')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['string' => __('validation.max.string', ['attribute' => 'string', 'max' => 10])]);
});

it('can overwrite validation rules', function () {
    RequestData::$rules = ['string' => 'max:200'];

    performRequest('Accepted string longer then 10 characters from attribute on data object')
        ->assertOk()
        ->assertJson(['given' => 'Accepted string longer then 10 characters from attribute on data object']);
});

it('can overwrite rules like a regular laravel request', function () {
    RequestData::$rules = ['string' => 'min:10|numeric'];

    performRequest('Too short')
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'string' => [
                __('validation.min.string', ['attribute' => 'string', 'min' => 10]),
                __('validation.numeric', ['attribute' => 'string']),
            ],
        ]);

    RequestData::$rules = ['string' => ['min:10', 'numeric']];

    performRequest('Too short')
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'string' => [
                __('validation.min.string', ['attribute' => 'string', 'min' => 10]),
                __('validation.numeric', ['attribute' => 'string']),
            ],
        ]);

    RequestData::$rules = ['string' => Rule::in(['alpha', 'beta'])];

    performRequest('Not in list')
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'string' => __('validation.in', ['attribute' => 'string']),
        ]);
});

it('can overwrite validation messages', function () {
    RequestData::$messages = [
        'max' => 'too long',
    ];

    performRequest('Hello World')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['string' => 'too long']);
});

it('can overwrite validation attributes', function () {
    RequestData::$attributes = [
        'string' => 'data property',
    ];

    performRequest('Hello world')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['string' => __('validation.max.string', ['attribute' => 'data property', 'max' => 10])]);
});

it('can change the validator', function () {
    RequestData::$validatorClosure = fn (Validator $validator) => $validator->setRules([]);

    performRequest('Hello world')
        ->assertOk()
        ->assertJson(['given' => 'Hello world']);
});

it('can nest data', function () {
    DataBlueprintFactory::new('SingleNestedData')->withProperty(
        DataPropertyBlueprintFactory::new('simple')->withType(SimpleData::class)
    )->create();

    Route::post('/nested-route', function (\SingleNestedData $data) {
        return ['given' => $data->simple->string];
    });

    $this->postJson('/nested-route', [
        'simple' => [
            'string' => 'Hello World',
        ],
    ])
        ->assertOk()
        ->assertSee('Hello World');

    $this->postJson('/nested-route', [
        'simple' => [
            'string' => 5333,
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['simple.string' => 'The simple.string must be a string.']);
});

it('can nest collections of data', function () {
    DataBlueprintFactory::new('CollectionNestedData')->withProperty(
        DataPropertyBlueprintFactory::dataCollection('simple_collection', SimpleData::class)
    )->create();

    Route::post('/nested-route', function (\CollectionNestedData $data) {
        return ['given' => $data->simple_collection->all()];
    });

    $this->postJson('/nested-route', [
        'simple_collection' => [
            [
                'string' => 'Hello World',
            ],
            [
                'string' => 'Goodbye',
            ],
        ],
    ])
        ->assertOk()
        ->assertJson([
            'given' => [
                [
                    'string' => 'Hello World',
                ],
                [
                    'string' => 'Goodbye',
                ],
            ],
        ]);

    $this->postJson('/nested-route', [
        'simple_collection' => [
            [
                'string' => 'Hello World',
            ],
            [
                'string' => 3.14,
            ],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['simple_collection.1.string' => 'The simple_collection.1.string must be a string.']);
});

it('can check for authorisation', function () {
    RequestData::$enableAuthorizeFailure = true;

    performRequest('Hello')->assertStatus(403);
});

it('can skip validation on certain properties', function () {
    DataBlueprintFactory::new('ValidationSkippeableDataFromRequest')
        ->withProperty(
            DataPropertyBlueprintFactory::new('first_name')
            ->withType('string')
        )
        ->withProperty(
            DataPropertyBlueprintFactory::new('last_name')
            ->withAttribute(WithoutValidation::class)
            ->withAttribute(Max::class, [2])
            ->withType('string')
        )
        ->create();

    Route::post('/other-route', function (\ValidationSkippeableDataFromRequest $data) {
        return ['first_name' => $data->first_name, 'last_name' => $data->last_name];
    });

    $this->postJson('/other-route', [
        'first_name' => 'Rick', 'last_name' => 'Astley',
    ])
        ->assertOk()
        ->assertJson(['first_name' => 'Rick', 'last_name' => 'Astley']);
});

it('can manually override how the data object will be constructed', function () {
    DataBlueprintFactory::new('OverrideableDataFromRequest')
        ->withProperty(
            DataPropertyBlueprintFactory::new('name')
            ->withAttribute(WithoutValidation::class)
            ->withType('string')
        )
        ->withMethod(
            DataMagicMethodFactory::new('fromRequest')
                ->withInputType(Request::class, 'request')
                ->withBody('return new self("{$request->input(\'first_name\')} {$request->input(\'last_name\')}");')
        )
        ->create();

    Route::post('/other-route', function (\OverrideableDataFromRequest $data) {
        return ['name' => $data->name];
    });

    $this->postJson('/other-route', [
        'first_name' => 'Rick',
        'last_name' => 'Astley',
    ])
        ->assertOk()
        ->assertJson(['name' => 'Rick Astley']);
});

it('wont validate optional properties', function () {
    DataBlueprintFactory::new('UndefinableDataFromRequest')
        ->withProperty(
            DataPropertyBlueprintFactory::new('name')
                ->withType('string'),
            DataPropertyBlueprintFactory::new('age')
                ->withType('int', Optional::class)
        )
        ->create();

    Route::post('/other-route', function (\UndefinableDataFromRequest $data) {
        return $data->toArray();
    });

    $this->postJson('/other-route', [
        'name' => 'Rick Astley',
        'age' => 42,
    ])
        ->assertOk()
        ->assertJson(['name' => 'Rick Astley', 'age' => 42]);

    $this->postJson('/other-route', [
        'name' => 'Rick Astley',
    ])
        ->assertOk()
        ->assertJson(['name' => 'Rick Astley']);
});

// Helpers
function performRequest(string $string): TestResponse
{
    return test()->postJson('/example-route', [
        'string' => $string,
    ]);
}
