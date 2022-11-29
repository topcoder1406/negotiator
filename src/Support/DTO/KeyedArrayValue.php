<?php

namespace Savks\Negotiator\Support\DTO;

use Closure;
use Savks\Negotiator\Support\DTO\ArrayValue\Item;

use Savks\Negotiator\Exceptions\{
        DTOException,
        UnexpectedFinalValue
};

class KeyedArrayValue extends Value
{
    public function __construct(
        protected readonly mixed $source,
        protected readonly string|Closure $key,
        protected readonly string|Closure $iterator,
        protected readonly string|Closure|null $accessor = null
    ) {
    }

    protected function finalize(): mixed
    {
        if ($this->accessor === null) {
            $value = $this->source;
        } elseif (\is_string($this->accessor)) {
            $value = \data_get($this->source, $this->accessor);
        } else {
            $value = ($this->accessor)($this->source);
        }

        if ($value === null) {
            return null;
        }

        if (! \is_iterable($value)) {
            throw new UnexpectedFinalValue(
                static::class,
                'iterable',
                $value,
                $this->accessor
            );
        }

        $result = [];

        foreach ($value as $item) {
            $listItemValue = ($this->iterator)(
                new Item($item)
            );

            if (! $listItemValue instanceof Value) {
                throw new DTOException('List iterator must return value that extends "' . Value::class . '"');
            }

            if (\is_string($this->key)) {
                $keyValue = \data_get($item, $this->key);
            } else {
                $keyValue = ($this->key)($item);
            }

            if (! \is_string($keyValue)) {
                throw new DTOException('Keyed list key must be string, given "' . \gettype($keyValue) . '"');
            }

            $result[$keyValue] = $listItemValue->compile();
        }

        return $result ?: null;
    }
}
