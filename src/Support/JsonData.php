<?php

declare(strict_types=1);

namespace Wtroiano\BoundedJson\Support;

use InvalidArgumentException;
use stdClass;

final class JsonData
{
    public static function object(mixed $value): stdClass
    {
        throw_unless($value instanceof stdClass, InvalidArgumentException::class, 'JSON value must be an object.');

        return $value;
    }

    public static function string(mixed $value): string
    {
        throw_unless(is_string($value), InvalidArgumentException::class, 'JSON value must be a string.');

        return $value;
    }

    public static function integer(mixed $value): int
    {
        throw_unless(is_int($value), InvalidArgumentException::class, 'JSON value must be an integer.');

        return $value;
    }

    public static function boolean(mixed $value): bool
    {
        throw_unless(is_bool($value), InvalidArgumentException::class, 'JSON value must be a boolean.');

        return $value;
    }

    /** @return array<string, mixed> */
    public static function map(mixed $value): array
    {
        if ($value instanceof stdClass) {
            $value = get_object_vars($value);
        }

        throw_unless(is_array($value), InvalidArgumentException::class, 'JSON value must be a map.');

        $result = [];
        foreach ($value as $key => $item) {
            throw_unless(is_string($key), InvalidArgumentException::class, 'JSON map keys must be strings.');

            $result[$key] = $item;
        }

        return $result;
    }

    /** @return array<string, stdClass> */
    public static function objects(mixed $value): array
    {
        return array_map(self::object(...), self::map($value));
    }

    /** @return list<mixed> */
    public static function list(mixed $value): array
    {
        throw_if(! is_array($value) || ! array_is_list($value), InvalidArgumentException::class, 'JSON value must be a list.');

        return $value;
    }

    /** @return list<stdClass> */
    public static function objectList(mixed $value): array
    {
        return array_map(self::object(...), self::list($value));
    }

    /** @return list<string> */
    public static function strings(mixed $value): array
    {
        return array_map(self::string(...), self::list($value));
    }
}
