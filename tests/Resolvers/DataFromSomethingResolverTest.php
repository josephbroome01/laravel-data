<?php

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PHPUnit\Util\Exception;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Tests\Fakes\DummyDto;
use Spatie\LaravelData\Tests\Fakes\DummyModel;
use Spatie\LaravelData\Tests\Fakes\DummyModelWithCasts;
use Spatie\LaravelData\Tests\TestCase;

uses(TestCase::class);

it('can create data from a custom method', function () {
    $data = new class ('') extends Data {
        public function __construct(public string $string) {
        }

        public static function fromString(string $string): static
        {
            return new self($string);
        }

        public static function fromDto(DummyDto $dto) {
            return new self($dto->artist);
        }

        public static function fromArray(array $payload) {
            return new self($payload['string']);
        }
    };

    $this->assertEquals(new $data('Hello World'), $data::from('Hello World'));
    $this->assertEquals(new $data('Rick Astley'), $data::from(DummyDto::rick()));
    $this->assertEquals(new $data('Hello World'), $data::from(['string' => 'Hello World']));
    $this->assertEquals(new $data('Hello World'), $data::from(DummyModelWithCasts::make(['string' => 'Hello World'])));
});

it('can create data from a custom method with an interface parameter', function () {
    $data = new class ('') extends Data {
        public function __construct(public string $string) {
        }

        public static function fromInterface(Arrayable $arrayable) {
            return new self($arrayable->toArray()['string']);
        }
    };

    $interfaceable = new class () implements Arrayable {
        public function toArray() {
            return [
                'string' => 'Rick Astley',
            ];
        }
    };

    $this->assertEquals(new $data('Rick Astley'), $data::from($interfaceable));
});

it('can create data from a custom method with an inherited parameter', function () {
    $data = new class ('') extends Data {
        public function __construct(public string $string) {
        }

        public static function fromModel(Model $model) {
            return new self($model->string);
        }
    };

    $inherited = new DummyModel(['string' => 'Rick Astley']);

    $this->assertEquals(new $data('Rick Astley'), $data::from($inherited));
});

it('can create data from a custom method and always takes the nearest type', function () {
    $data = new class ('') extends Data {
        public function __construct(public string $string) {
        }

        public static function fromModel(Model $arrayable) {
            throw new Exception("Cannot be called");
        }

        public static function fromDummyModel(DummyModel $model) {
            return new self($model->string);
        }
    };

    $inherited = new DummyModel(['string' => 'Rick Astley']);

    $this->assertEquals(new $data('Rick Astley'), $data::from($inherited));
});

it('can create data from a custom optional method', function () {
    $data = new class ('') extends Data {
        public function __construct(public string $string) {
        }

        public static function optionalString(string $string): static
        {
            return new self($string);
        }

        public static function optionalDto(DummyDto $dto) {
            return new self($dto->artist);
        }

        public static function optionalArray(array $payload) {
            return new self($payload['string']);
        }
    };

    $this->assertEquals(new $data('Hello World'), $data::optional('Hello World'));
    $this->assertEquals(new $data('Rick Astley'), $data::optional(DummyDto::rick()));
    $this->assertEquals(new $data('Hello World'), $data::optional(['string' => 'Hello World']));
    $this->assertEquals(new $data('Hello World'), $data::optional(DummyModel::make(['string' => 'Hello World'])));

    $this->assertNull($data::optional(null));
});

it('can resolve validation dependencies for messages', function () {
    $requestMock = $this->mock(Request::class);
    $requestMock->expects('input')->andReturns('value');
    app()->bind(Request::class, fn () => $requestMock);

    $data = new class () extends Data {
        public string $name;
        public static function rules() {
            return [
                'name' => ['required'],
            ];
        }
        public static function messages(Request $request): array
        {
            return [
                'name.required' => $request->input('key') === 'value' ? 'Name is required' : 'Bad',
            ];
        }
    };
    $this->expectException(ValidationException::class);
    $this->expectExceptionMessage('Name is required');
    $data::validate(['name' => '']);
});

it('can resolve validation dependencies for attributes', function () {
    $requestMock = $this->mock(Request::class);
    $requestMock->expects('input')->andReturns('value');
    app()->bind(Request::class, fn () => $requestMock);

    $data = new class () extends Data {
        public string $name;
        public static function rules() {
            return [
                'name' => ['required'],
            ];
        }
        public static function attributes(Request $request): array
        {
            return [
                'name' => $request->input('key') === 'value' ? 'Another name' : 'Bad',
            ];
        }
    };
    $this->expectException(ValidationException::class);
    $this->expectExceptionMessage('The Another name field is required');
    $data::validate(['name' => '']);
});

it('can resolve payload dependency for rules', function () {
    $data = new class () extends Data {
        public string $payment_method;
        public ?string $paypal_email;

        public static function rules(array $payload) {
            return [
                'payment_method' => ['required'],
                'paypal_email' => Rule::requiredIf($payload['payment_method'] === 'paypal'),
            ];
        }
    };

    $result = $data::validate(['payment_method' => 'credit_card']);
    $this->assertEquals([
        'payment_method' => 'credit_card',
        'paypal_email' => null,
    ], $result->toArray());

    $this->expectException(ValidationException::class);
    $this->expectExceptionMessage('The paypal email field is required');
    $data::validate(['payment_method' => 'paypal']);
});
