<?php

namespace Spatie\LaravelData\Concerns;

use Closure;
use Spatie\LaravelData\Contracts\IncludeableData as IncludeableDataContract;
use Spatie\LaravelData\Support\PartialsParser;
use Spatie\LaravelData\Support\PartialTrees;

trait IncludeableData
{
    protected ?PartialTrees $partialTrees = null;

    protected array $includes = [];

    protected array $excludes = [];

    protected array $only = [];

    protected array $except = [];

    public function withPartialTrees(
        PartialTrees $partialTrees,
    ): IncludeableDataContract {
        $this->partialTrees = $partialTrees;

        return $this;
    }

    public function include(string ...$includes): IncludeableDataContract
    {
        $this->includes = array_unique(array_merge($this->includes, $includes));

        return $this;
    }

    public function exclude(string ...$excludes): IncludeableDataContract
    {
        $this->excludes = array_unique(array_merge($this->excludes, $excludes));

        return $this;
    }

    public function only(string ...$only): IncludeableDataContract
    {
        $this->only = array_unique(array_merge($this->only, $only));

        return $this;
    }

    public function except(string ...$except): IncludeableDataContract
    {
        $this->except = array_unique(array_merge($this->except, $except));

        return $this;
    }

    public function onlyWhen(string $only, bool|Closure $condition): IncludeableDataContract
    {
        $condition = $condition instanceof Closure
            ? $condition($this)
            : $condition;

        if ($condition) {
            $this->only($only);
        }

        return $this;
    }

    public function exceptWhen(string $except, bool|Closure $condition): IncludeableDataContract
    {
        $condition = $condition instanceof Closure
            ? $condition($this)
            : $condition;

        if ($condition) {
            $this->except($except);
        }

        return $this;
    }

    public function getPartialTrees(): PartialTrees
    {
        if ($this->partialTrees) {
            return $this->partialTrees;
        }

        return new PartialTrees(
            ! empty($this->includes) ? (new PartialsParser())->execute($this->includes) : null,
            ! empty($this->excludes) ? (new PartialsParser())->execute($this->excludes) : null,
            ! empty($this->only) ? (new PartialsParser())->execute($this->only) : null,
            ! empty($this->except) ? (new PartialsParser())->execute($this->except) : null,
        );
    }
}
