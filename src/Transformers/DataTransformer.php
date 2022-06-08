<?php

namespace Spatie\LaravelData\Transformers;

use Illuminate\Support\Arr;
use Spatie\LaravelData\Contracts\AppendableData;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Contracts\BaseDataCollectable;
use Spatie\LaravelData\Contracts\IncludeableData;
use Spatie\LaravelData\Contracts\TransformableData;
use Spatie\LaravelData\Contracts\WrappableData;
use Spatie\LaravelData\Lazy;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Lazy\ConditionalLazy;
use Spatie\LaravelData\Support\Lazy\RelationalLazy;
use Spatie\LaravelData\Support\PartialTrees;
use Spatie\LaravelData\Support\Wrapping\WrapExecutionType;
use TypeError;

class DataTransformer
{
    private DataConfig $config;

    public static function create(
        bool $transformValues,
        WrapExecutionType $wrapExecutionType
    ): self {
        return new self($transformValues, $wrapExecutionType);
    }

    public function __construct(
        protected bool $transformValues,
        protected WrapExecutionType $wrapExecutionType,
    ) {
        $this->config = app(DataConfig::class);
    }

    public function transform(TransformableData $data): array
    {
        $transformed = $this->resolvePayload($data);

        if ($data instanceof WrappableData && $this->wrapExecutionType->shouldExecute()) {
            $transformed = $data->getWrap()->wrap($transformed);
        }

        if ($data instanceof AppendableData) {
            $transformed = array_merge($transformed, $data->getAdditionalData());
        }

        return $transformed;
    }

    protected function resolvePayload(TransformableData $data): array
    {
        $trees = $data instanceof IncludeableData
            ? $data->getPartialTrees()
            : new PartialTrees();

        $dataClass = $this->config->getDataClass($data::class);

        return $dataClass
            ->properties
            ->reduce(function (array $payload, DataProperty $property) use ($data, $trees) {
                $name = $property->name;

                if (! $this->shouldIncludeProperty($name, $data->{$name}, $trees)) {
                    return $payload;
                }

                $value = $this->resolvePropertyValue(
                    $property,
                    $data->{$name},
                    $trees->getNested($name),
                );

                if ($value instanceof Optional) {
                    return $payload;
                }

                if ($property->outputMappedName) {
                    $name = $property->outputMappedName;
                }

                $payload[$name] = $value;

                return $payload;
            }, []);
    }

    protected function shouldIncludeProperty(
        string $name,
        mixed $value,
        PartialTrees $trees,
    ): bool {
        if ($value instanceof Optional) {
            return false;
        }

        if ($this->isPropertyHidden($name, $trees)) {
            return false;
        }

        if (! $value instanceof Lazy) {
            return true;
        }

        if ($value instanceof RelationalLazy || $value instanceof ConditionalLazy) {
            return $value->shouldBeIncluded();
        }

        if ($this->isPropertyLazyExcluded($name, $trees)) {
            return false;
        }

        return $this->isPropertyLazyIncluded($name, $value, $trees);
    }

    protected function isPropertyHidden(
        string $name,
        PartialTrees $trees,
    ): bool {
        if ($trees->except === ['*']) {
            return true;
        }

        if (array_key_exists($name, $trees->except ?? []) && empty($trees->except[$name])) {
            return true;
        }

        if ($trees->except !== null) {
            return false;
        }

        if ($trees->only === ['*']) {
            return false;
        }

        if (array_key_exists($name, $trees->only ?? [])) {
            return false;
        }

        if ($trees->only !== null) {
            return true;
        }

        return false;
    }

    protected function isPropertyLazyExcluded(
        string $name,
        PartialTrees $trees,
    ): bool {
        if ($trees->lazyExcluded === ['*']) {
            return true;
        }

        return array_key_exists($name, $trees->lazyExcluded ?? []);
    }

    protected function isPropertyLazyIncluded(
        string $name,
        Lazy $value,
        PartialTrees $trees,
    ): bool {
        if ($trees->lazyIncluded === ['*']) {
            return true;
        }

        if ($value->isDefaultIncluded()) {
            return true;
        }

        return array_key_exists($name, $trees->lazyIncluded ?? []);
    }

    protected function resolvePropertyValue(
        DataProperty $property,
        mixed $value,
        PartialTrees $trees
    ): mixed {
        if ($value instanceof Lazy) {
            $value = $value->resolve();
        }

        if ($value === null) {
            return null;
        }

        if (is_array($value) && ! empty($trees->only)) {
            $value = Arr::only($value, array_keys($trees->only));
        }

        if (is_array($value) && ! empty($trees->except)) {
            $value = Arr::except($value, array_keys($trees->except));
        }

        if ($transformer = $this->resolveTransformerForValue($property, $value)) {
            return $transformer->transform($property, $value);
        }

        if (! $value instanceof BaseData && ! $value instanceof BaseDataCollectable) {
            return $value;
        }

        if ($value instanceof IncludeableData) {
            $value->withPartialTrees($trees);
        }

        $wrapExecutionType = match (true) {
            $value instanceof BaseData && $this->wrapExecutionType === WrapExecutionType::Enabled => WrapExecutionType::TemporarilyDisabled,
            $value instanceof BaseData && $this->wrapExecutionType === WrapExecutionType::Disabled => WrapExecutionType::Disabled,
            $value instanceof BaseData && $this->wrapExecutionType === WrapExecutionType::TemporarilyDisabled => WrapExecutionType::TemporarilyDisabled,
            $value instanceof BaseDataCollectable && $this->wrapExecutionType === WrapExecutionType::Enabled => WrapExecutionType::Enabled,
            $value instanceof BaseDataCollectable && $this->wrapExecutionType === WrapExecutionType::Disabled => WrapExecutionType::Disabled,
            $value instanceof BaseDataCollectable && $this->wrapExecutionType === WrapExecutionType::TemporarilyDisabled => WrapExecutionType::Enabled,
            default => throw new TypeError('Invalid wrap execution type')
        };

        if ($value instanceof TransformableData && $this->transformValues) {
            return $value->transform($this->transformValues, $wrapExecutionType);
        }

        return $value;
    }

    private function resolveTransformerForValue(
        DataProperty $property,
        mixed $value,
    ): ?Transformer {
        if (! $this->transformValues) {
            return null;
        }

        $transformer = $property->transformer ?? $this->config->findGlobalTransformerForValue($value);

        $shouldUseDefaultDataTransformer = $transformer instanceof ArrayableTransformer
            && ($property->type->isDataObject || $property->type->isDataCollectable);

        if ($shouldUseDefaultDataTransformer) {
            return null;
        }

        return $transformer;
    }
}
