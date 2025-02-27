<?php

namespace Savks\Negotiator\Support\DTO;

use Closure;
use Savks\Negotiator\Exceptions\UnexpectedValue;
use Savks\Negotiator\Support\DTO\ArrayValue\Item;

use Savks\Negotiator\Support\Types\{
    RecordType,
    Type,
    Types
};

class KeyedArrayValue extends NullableValue
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
        $value = $this->resolveValueFromAccessor(
            $this->accessor,
            $this->source,
            $this->sourcesTrace
        );

        if ($this->accessor && last($this->sourcesTrace) !== $this->source) {
            $this->sourcesTrace[] = $this->source;
        }

        if ($value === null) {
            return null;
        }

        if (! \is_iterable($value)) {
            throw new UnexpectedValue('iterable', $value);
        }

        $result = [];

        $index = 0;

        foreach ($value as $key => $item) {
            $listItemValue = ($this->iterator)(
                new Item($index++, $item, $this->sourcesTrace)
            );

            if (! $listItemValue instanceof Value) {
                throw new UnexpectedValue(
                    Value::class,
                    $listItemValue
                );
            }

            if (\is_string($this->key)) {
                $keyValue = \data_get($item, $this->key);
            } else {
                $keyValue = ($this->key)($item, $key, ...$this->sourcesTrace);
            }

            if (! \is_string($keyValue)) {
                throw new UnexpectedValue('string', $keyValue);
            }

            try {
                $result[$keyValue] = $listItemValue->compile();
            } catch (UnexpectedValue $e) {
                throw UnexpectedValue::wrap($e, "{$key}({$keyValue})");
            }
        }

        return $result ?: null;
    }

    protected function types(): Type|Types
    {
        /** @var Value $listItem */
        $listItem = ($this->iterator)(
            new Item(0, null)
        );

        return new RecordType(
            valueType: $listItem->compileTypes()
        );
    }
}
