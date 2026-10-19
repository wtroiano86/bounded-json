<?php

declare(strict_types=1);

namespace Wtroiano\BoundedJson\Support;

use RuntimeException;
use Wtroiano\BoundedJson\Options\SchemaLimits;

final readonly class SchemaValueValidator
{
    private const array Keywords = ['$schema', '$id', '$defs', 'definitions', '$ref', 'type', 'properties', 'required', 'additionalProperties', 'items', 'minItems', 'maxItems', 'uniqueItems', 'minProperties', 'maxProperties', 'minLength', 'maxLength', 'pattern', 'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf', 'enum', 'const', 'allOf', 'anyOf', 'oneOf', 'not', 'title', 'description', 'default', 'examples', 'deprecated', 'readOnly', 'writeOnly'];

    public function __construct(private SchemaLimits $limits = new SchemaLimits) {}

    /** @param array<array-key, mixed> $schema */
    public function validate(array $schema, mixed $value): void
    {
        $this->definition($schema, $schema, 0);
        $this->check($schema, $value, $schema, 0);
    }

    /** @param array<array-key, mixed> $root */
    private function definition(mixed $schema, array $root, int $depth): void
    {
        throw_if($depth > $this->limits->maxEvaluationDepth, RuntimeException::class, 'Schema nesting exceeds '.$this->limits->maxEvaluationDepth.' evaluation levels.');

        if (is_bool($schema)) {
            return;
        }

        $schema = $this->map($schema);
        foreach (array_keys($schema) as $key) {
            throw_unless(in_array($key, self::Keywords, true), RuntimeException::class, 'Unsupported schema keyword: '.$key.'.');
        }

        if (isset($schema['$ref'])) {
            $this->definition($this->reference($this->string($schema['$ref']), $root), $root, $depth + 1);
        }

        foreach (['properties', '$defs', 'definitions'] as $key) {
            foreach ($this->map($schema[$key] ?? []) as $child) {
                $this->definition($child, $root, $depth + 1);
            }
        }

        foreach (['allOf', 'anyOf', 'oneOf'] as $key) {
            foreach ($this->map($schema[$key] ?? []) as $child) {
                $this->definition($child, $root, $depth + 1);
            }
        }

        foreach (['items', 'additionalProperties', 'not'] as $key) {
            if (isset($schema[$key])) {
                $this->definition($schema[$key], $root, $depth + 1);
            }
        }
    }

    /** @param array<array-key, mixed> $root */
    private function check(mixed $schema, mixed $value, array $root, int $depth): void
    {
        throw_if($depth > $this->limits->maxEvaluationDepth, RuntimeException::class, 'Value validation exceeds '.$this->limits->maxEvaluationDepth.' evaluation levels.');
        $this->require($schema !== false);

        if ($schema === true) {
            return;
        }

        $schema = $this->map($schema);
        if (isset($schema['$ref'])) {
            $this->check($this->reference($this->string($schema['$ref']), $root), $value, $root, $depth + 1);
        }

        if (isset($schema['type'])) {
            $types = (array) $schema['type'];
            $matches = false;
            foreach ($types as $type) {
                $matches = $matches || match ($type) {
                    'object' => is_object($value), 'array' => is_array($value), 'string' => is_string($value),
                    'integer' => is_int($value) || (is_float($value) && floor($value) === $value),
                    'number' => is_int($value) || is_float($value), 'boolean' => is_bool($value), 'null' => $value === null,
                    default => throw new RuntimeException('Unsupported schema type.'),
                };
            }

            $this->require($matches);
        }

        if (isset($schema['enum'])) {
            $this->require(count(array_filter($this->map($schema['enum']), fn (mixed $candidate): bool => $this->equal($candidate, $value))) > 0);
        }

        if (array_key_exists('const', $schema)) {
            $this->require($this->equal($schema['const'], $value));
        }

        foreach (['allOf', 'anyOf', 'oneOf'] as $key) {
            if (! isset($schema[$key])) {
                continue;
            }

            $matches = 0;
            foreach ($this->map($schema[$key]) as $alternative) {
                try {
                    $this->check($alternative, $value, $root, $depth + 1);
                    $matches++;
                } catch (RuntimeException) {
                    // Other alternatives may match.
                }
            }

            $this->require(match ($key) {
                'allOf' => $matches === count($this->map($schema[$key])), 'anyOf' => $matches > 0, 'oneOf' => $matches === 1
            });
        }

        if (isset($schema['not'])) {
            $matches = true;
            try {
                $this->check($schema['not'], $value, $root, $depth + 1);
            } catch (RuntimeException) {
                $matches = false;
            }

            $this->require(! $matches);
        }

        if (is_object($value)) {
            $values = get_object_vars($value);
            foreach ($this->map($schema['required'] ?? []) as $key) {
                $this->require(array_key_exists($this->string($key), $values));
            }

            $this->bounds(count($values), $schema, 'minProperties', 'maxProperties');
            foreach ($values as $key => $item) {
                $child = $this->map($schema['properties'] ?? [])[$key] ?? ($schema['additionalProperties'] ?? true);
                $this->check($child, $item, $root, $depth + 1);
            }
        } elseif (is_array($value)) {
            $this->bounds(count($value), $schema, 'minItems', 'maxItems');
            if ($schema['uniqueItems'] ?? false) {
                $counter = count($value);
                for ($i = 0; $i < $counter; $i++) {
                    for ($j = 0; $j < $i; $j++) {
                        $this->require(! $this->equal($value[$i], $value[$j]));
                    }
                }
            }

            foreach ($value as $item) {
                $this->check($schema['items'] ?? true, $item, $root, $depth + 1);
            }
        } elseif (is_string($value)) {
            $this->bounds(mb_strlen($value), $schema, 'minLength', 'maxLength');
            if (isset($schema['pattern'])) {
                $this->require(preg_match('~'.str_replace('~', '\\~', $this->string($schema['pattern'])).'~u', $value) === 1);
            }
        } elseif (is_int($value) || is_float($value)) {
            $this->bounds($value, $schema, 'minimum', 'maximum');
            $this->require(! isset($schema['exclusiveMinimum']) || $value > $schema['exclusiveMinimum']);
            $this->require(! isset($schema['exclusiveMaximum']) || $value < $schema['exclusiveMaximum']);
            if (isset($schema['multipleOf'])) {
                $divisor = $schema['multipleOf'];
                throw_if(! is_int($divisor) && ! is_float($divisor), RuntimeException::class, 'Invalid schema divisor.');

                $this->require($divisor > 0 && abs($value / $divisor - round($value / $divisor)) < 1e-9);
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $root
     * @return array<array-key, mixed>|bool
     */
    private function reference(string $ref, array $root): array|bool
    {
        throw_unless(str_starts_with($ref, '#/'), RuntimeException::class, 'Only local schema references are supported.');

        $current = $root;
        foreach (explode('/', mb_substr($ref, 2)) as $key) {
            $key = str_replace(['~1', '~0'], ['/', '~'], $key);
            throw_if(! is_array($current) || ! array_key_exists($key, $current), RuntimeException::class, 'Invalid schema reference.');

            $current = $current[$key];
        }

        throw_if(! is_array($current) && ! is_bool($current), RuntimeException::class, 'Invalid schema reference.');

        return $current;
    }

    private function equal(mixed $left, mixed $right): bool
    {
        if (is_object($right)) {
            $right = get_object_vars($right);
        }

        if (is_object($left)) {
            $left = get_object_vars($left);
        }

        if (is_array($left) && is_array($right)) {
            if (count($left) !== count($right) || array_diff_key($left, $right) !== []) {
                return false;
            }

            return array_all($left, fn (mixed $value, int|string $key): bool => $this->equal($value, $right[$key]));
        }

        return is_numeric($left) && is_numeric($right) && ! is_string($left) && ! is_string($right) ? (float) $left === (float) $right : $left === $right;
    }

    /** @param array<array-key, mixed> $schema */
    private function bounds(int|float $value, array $schema, string $min, string $max): void
    {
        $this->require(! isset($schema[$min]) || $value >= $schema[$min]);
        $this->require(! isset($schema[$max]) || $value <= $schema[$max]);
    }

    /** @return array<array-key, mixed> */
    private function map(mixed $value): array
    {
        throw_unless(is_array($value), RuntimeException::class, 'Invalid schema structure.');

        return $value;
    }

    private function string(mixed $value): string
    {
        throw_unless(is_string($value), RuntimeException::class, 'Invalid schema string.');

        return $value;
    }

    private function require(bool $condition): void
    {
        throw_unless($condition, RuntimeException::class, 'Value does not match the schema.');
    }
}
