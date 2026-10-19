<?php

declare(strict_types=1);

namespace Wtroiano\BoundedJson\Support;

use InvalidArgumentException;
use RuntimeException;
use stdClass;
use Wtroiano\BoundedJson\Exceptions\JsonValueInvalid;
use Wtroiano\BoundedJson\Options\SchemaLimits;

final readonly class BoundedSchemaValidator
{
    private const array Keywords = ['type', 'properties', 'required', 'additionalProperties', 'items', 'minItems', 'maxItems', 'minLength', 'maxLength', 'minimum', 'maximum', 'enum', 'title', 'description'];

    private const array Types = ['object', 'array', 'string', 'number', 'integer', 'boolean', 'null'];

    public function __construct(
        private CanonicalJson $canonicalJson,
        private SchemaValueValidator $valueValidator,
        private SchemaLimits $limits = new SchemaLimits,
    ) {}

    public function validateDefinition(mixed $schema): void
    {
        $this->canonicalJson->encode($schema);
        $this->definition($schema, 0);
    }

    public function validateValue(stdClass $schema, mixed $value): void
    {
        $this->validateDefinition($schema);
        $this->validateRuntimeValue($schema, $value);
    }

    public function validateRuntimeValue(stdClass $schema, mixed $value): void
    {
        $this->canonicalJson->encode($value);
        try {
            $this->valueValidator->validate($this->toArray($schema), $value);
            $this->validateEnums($schema, $value);
        } catch (RuntimeException $runtimeException) {
            throw new JsonValueInvalid('Value does not satisfy the bounded schema.', $runtimeException->getCode(), previous: $runtimeException);
        }
    }

    private function definition(mixed $schema, int $depth): void
    {
        throw_unless($schema instanceof stdClass && $depth <= $this->limits->maxDepth, InvalidArgumentException::class, 'Schema must be an object with at most '.$this->limits->maxDepth.' nested levels.');
        $fields = JsonData::map($schema);
        throw_unless(array_key_exists('type', $fields), InvalidArgumentException::class, 'Schema type is required.');

        foreach (array_keys($fields) as $keyword) {
            throw_unless(in_array($keyword, self::Keywords, true), InvalidArgumentException::class, 'Unsupported bounded schema keyword: '.$keyword);
        }

        $types = is_array($fields['type']) ? $fields['type'] : [$fields['type']];
        throw_unless(array_is_list($types), InvalidArgumentException::class, 'Schema types must be a list.');
        if (count($types) !== 1 && (count($types) !== 2 || ! in_array('null', $types, true))) {
            throw new InvalidArgumentException('Schema type must name one type or one type plus null.');
        }
        foreach ($types as $type) {
            throw_unless(is_string($type) && in_array($type, self::Types, true), InvalidArgumentException::class, 'Unsupported schema type.');
        }

        throw_unless(count(array_unique(JsonData::strings($types))) === count($types), InvalidArgumentException::class, 'Duplicate schema type.');

        if (count($types) === 2) {
            throw_unless($types[0] !== 'null' || $types[1] !== 'null', InvalidArgumentException::class, 'Nullable schema requires a non-null type.');
        }

        $primary = $types[0] === 'null' && count($types) === 2 ? $types[1] : $types[0];
        foreach (['title', 'description'] as $keyword) {
            if (array_key_exists($keyword, $fields)) {
                throw_unless(is_string($fields[$keyword]), InvalidArgumentException::class, 'Schema '.$keyword.' must be a string.');
            }
        }

        if ($primary === 'object') {
            throw_unless(isset($fields['properties']) && $fields['properties'] instanceof stdClass, InvalidArgumentException::class, 'Object schema requires properties.');
            throw_unless(($fields['additionalProperties'] ?? null) === false, InvalidArgumentException::class, 'Object schema must set additionalProperties to false.');
            throw_unless(! array_key_exists('required', $fields) || is_array($fields['required']), InvalidArgumentException::class, 'Required fields must be an array.');
            $properties = get_object_vars($fields['properties']);
            $required = $fields['required'] ?? [];
            throw_unless(array_is_list($required), InvalidArgumentException::class, 'Required fields must be a list.');
            foreach ($required as $name) {
                throw_unless(is_string($name), InvalidArgumentException::class, 'Required field names must be strings.');
            }

            throw_unless(count($required) === count(array_unique($required)), InvalidArgumentException::class, 'Duplicate required field.');
            foreach ($required as $name) {
                throw_unless(array_key_exists($name, $properties), InvalidArgumentException::class, 'Required field is not declared in properties.');
            }

            foreach ($properties as $child) {
                $this->definition($child, $depth + 1);
            }
        } else {
            $this->forbid($fields, ['properties', 'required', 'additionalProperties']);
        }

        if ($primary === 'array') {
            throw_unless(isset($fields['items']), InvalidArgumentException::class, 'Array schema requires items.');
            $this->definition($fields['items'], $depth + 1);
            $this->boundedInteger($fields, 'maxItems', $this->limits->maxArrayItems, true);
            $this->boundedInteger($fields, 'minItems', $this->limits->maxArrayItems);
            $this->orderedBounds($fields, 'minItems', 'maxItems');
        } else {
            $this->forbid($fields, ['items', 'minItems', 'maxItems']);
        }

        if ($primary === 'string') {
            $this->boundedInteger($fields, 'maxLength', $this->limits->maxStringLength, true);
            $this->boundedInteger($fields, 'minLength', $this->limits->maxStringLength);
            $this->orderedBounds($fields, 'minLength', 'maxLength');
        } else {
            $this->forbid($fields, ['minLength', 'maxLength']);
        }

        if ($primary === 'number' || $primary === 'integer') {
            foreach (['minimum', 'maximum'] as $keyword) {
                if (array_key_exists($keyword, $fields)) {
                    throw_unless((is_int($fields[$keyword]) || is_float($fields[$keyword])) && is_finite((float) $fields[$keyword]), InvalidArgumentException::class, 'Numeric schema bound must be finite.');
                }
            }

            $this->orderedBounds($fields, 'minimum', 'maximum');
        } else {
            $this->forbid($fields, ['minimum', 'maximum']);
        }

        if (array_key_exists('enum', $fields)) {
            throw_unless(is_array($fields['enum']) && array_is_list($fields['enum']) && $fields['enum'] !== [], InvalidArgumentException::class, 'Enum must be a nonempty list.');
            $seen = [];
            foreach ($fields['enum'] as $item) {
                $encoded = $this->canonicalJson->encode($item);
                throw_unless(! isset($seen[$encoded]), InvalidArgumentException::class, 'Duplicate enum entry.');
                $seen[$encoded] = true;
                $withoutEnum = clone $schema;
                unset($withoutEnum->enum);
                $this->valueValidator->validate($this->toArray($withoutEnum), $item);
                $this->validateEnums($withoutEnum, $item);
            }
        }
    }

    private function validateEnums(stdClass $schema, mixed $value): void
    {
        if (property_exists($schema, 'enum')) {
            $encoded = $this->canonicalJson->encode($value);
            throw_unless(array_any(JsonData::list($schema->enum), fn (mixed $candidate): bool => $this->canonicalJson->encode($candidate) === $encoded), InvalidArgumentException::class, 'Value does not match a bounded enum entry.');
        }

        if ($value instanceof stdClass && isset($schema->properties)) {
            foreach (get_object_vars($value) as $name => $item) {
                $this->validateEnums(JsonData::object(JsonData::object($schema->properties)->{$name}), $item);
            }
        } elseif (is_array($value) && isset($schema->items)) {
            foreach ($value as $item) {
                $this->validateEnums(JsonData::object($schema->items), $item);
            }
        }
    }

    /** @param array<string, mixed> $fields */
    private function boundedInteger(array $fields, string $keyword, int $maximum, bool $required = false): void
    {
        throw_unless(! $required || array_key_exists($keyword, $fields), InvalidArgumentException::class, 'Schema '.$keyword.' is required.');
        if (array_key_exists($keyword, $fields)) {
            throw_unless(is_int($fields[$keyword]) && $fields[$keyword] >= 0 && $fields[$keyword] <= $maximum, InvalidArgumentException::class, 'Schema '.$keyword.' must be an integer between 0 and '.$maximum.'.');
        }
    }

    /** @param array<string, mixed> $fields */
    private function orderedBounds(array $fields, string $lower, string $upper): void
    {
        throw_unless(! isset($fields[$lower], $fields[$upper]) || $fields[$lower] <= $fields[$upper], InvalidArgumentException::class, 'Schema bounds are incompatible.');
    }

    /** @param array<string, mixed> $fields
     * @param  list<string>  $keywords
     */
    private function forbid(array $fields, array $keywords): void
    {
        foreach ($keywords as $keyword) {
            throw_unless(! array_key_exists($keyword, $fields), InvalidArgumentException::class, 'Schema '.$keyword.' is incompatible with its type.');
        }
    }

    /** @return array<array-key, mixed> */
    private function toArray(mixed $value): array
    {
        throw_unless($value instanceof stdClass, InvalidArgumentException::class, 'Schema must be an object.');
        $result = [];
        foreach (get_object_vars($value) as $key => $item) {
            if ($item instanceof stdClass) {
                $result[$key] = $this->toArray($item);
            } elseif (is_array($item)) {
                $result[$key] = array_map(fn (mixed $child): mixed => $child instanceof stdClass ? $this->toArray($child) : $child, $item);
            } else {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
