<?php

declare(strict_types=1);

namespace Wtroiano\BoundedJson\Support;

use InvalidArgumentException;
use JsonException;
use stdClass;
use Wtroiano\BoundedJson\Options\JsonLimits;

final readonly class CanonicalJson
{
    private const int MaxSafeInteger = 9007199254740991;

    public function __construct(private JsonLimits $limits = new JsonLimits) {}

    public function decode(string $json): mixed
    {
        throw_if(mb_strlen($json, '8bit') > $this->limits->maxBytes, InvalidArgumentException::class, 'JSON exceeds the '.$this->limits->maxBytes.' byte payload limit.');

        try {
            // PHP counts containers from one; an empty container may sit at our maximum value depth.
            $value = json_decode($json, depth: $this->limits->maxDepth + 2, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException('Invalid JSON: '.$jsonException->getMessage(), $jsonException->getCode(), previous: $jsonException);
        }

        $this->rejectDuplicateKeys($json);
        $this->encode($value);

        return $value;
    }

    public function decodeObject(string $json): stdClass
    {
        $value = $this->decode($json);
        throw_unless($value instanceof stdClass, InvalidArgumentException::class, 'Expected a JSON object.');

        return $value;
    }

    public function hash(mixed $value): string
    {
        return hash('sha256', $this->encode($value));
    }

    public function encode(mixed $value): string
    {
        $json = $this->serialize($value, 0);
        throw_if(mb_strlen($json, '8bit') > $this->limits->maxBytes, InvalidArgumentException::class, 'JSON exceeds the '.$this->limits->maxBytes.' byte payload limit.');

        return $json;
    }

    private function serialize(mixed $value, int $depth): string
    {
        throw_if($depth > $this->limits->maxDepth, InvalidArgumentException::class, 'JSON nesting exceeds '.$this->limits->maxDepth.' levels.');

        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            return $this->string($value);
        }

        if (is_int($value)) {
            throw_if(abs($value) > self::MaxSafeInteger, InvalidArgumentException::class, 'JSON integer exceeds the interoperable safe range.');

            return (string) $value;
        }

        if (is_float($value)) {
            return $this->number($value);
        }

        if (is_array($value)) {
            throw_unless(array_is_list($value), InvalidArgumentException::class, 'JSON arrays must have contiguous numeric indexes; use an object for named fields.');

            return '['.implode(',', array_map(fn (mixed $item): string => $this->serialize($item, $depth + 1), $value)).']';
        }

        if (is_object($value) && $value::class === stdClass::class) {
            $properties = get_object_vars($value);
            uksort($properties, fn (string|int $left, string|int $right): int => strcmp(
                mb_convert_encoding((string) $left, 'UTF-16BE', 'UTF-8'),
                mb_convert_encoding((string) $right, 'UTF-16BE', 'UTF-8'),
            ));

            $parts = [];
            foreach ($properties as $key => $item) {
                $parts[] = $this->string((string) $key).':'.$this->serialize($item, $depth + 1);
            }

            return '{'.implode(',', $parts).'}';
        }

        throw new InvalidArgumentException('Only JSON values and plain objects can be canonicalized.');
    }

    private function string(string $value): string
    {
        try {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException('Invalid Unicode in JSON string.', $jsonException->getCode(), previous: $jsonException);
        }
    }

    private function number(float $value): string
    {
        throw_unless(is_finite($value), InvalidArgumentException::class, 'Non-finite JSON number.');

        throw_if(abs($value) > self::MaxSafeInteger && floor($value) === $value, InvalidArgumentException::class, 'JSON integer exceeds the interoperable safe range.');

        if ($value === 0.0) {
            return '0';
        }

        $precision = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');
        try {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        } finally {
            ini_set('serialize_precision', $precision);
        }

        if (! str_contains($encoded, 'e')) {
            return $encoded;
        }

        [$mantissa, $exponent] = explode('e', $encoded, 2);
        $exponent = (int) $exponent;
        $mantissa = str_ends_with($mantissa, '.0') ? mb_substr($mantissa, 0, -2) : $mantissa;

        if (abs($value) >= 1e-6 && abs($value) < 1e21) {
            $negative = str_starts_with($mantissa, '-');
            $unsigned = mb_ltrim($mantissa, '-');
            $digits = str_replace('.', '', $unsigned);
            $decimalPosition = mb_strpos($unsigned, '.') ?: mb_strlen($unsigned);
            $position = $decimalPosition + $exponent;
            $expanded = str_repeat('0', max(0, -$position)).$digits.str_repeat('0', max(0, $position - mb_strlen($digits)));
            $point = max(0, $position);
            $plain = mb_rtrim(mb_rtrim(mb_str_pad(mb_substr($expanded, 0, $point), 1, '0').'.'.mb_substr($expanded, $point), '0'), '.');

            return ($negative ? '-' : '').$plain;
        }

        return $mantissa.'e'.($exponent >= 0 ? '+' : '').$exponent;
    }

    private function rejectDuplicateKeys(string $json): void
    {
        // Possessive string matching avoids exhausting PCRE's JIT stack on long strings.
        $matched = preg_match_all('/"(?:[^"\\\\]++|\\\\.)*+"|[{}\[\],:]|-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?|true|false|null/u', $json, $matches);
        throw_if($matched === false, InvalidArgumentException::class, 'JSON token validation failed: '.preg_last_error_msg());
        $stack = [];

        foreach ($matches[0] as $token) {
            if (preg_match('/^-?[0-9]+$/', $token) === 1) {
                $digits = mb_ltrim($token, '-');
                $maximum = (string) self::MaxSafeInteger;
                throw_if(mb_strlen($digits) > mb_strlen($maximum) || (mb_strlen($digits) === mb_strlen($maximum) && strcmp($digits, $maximum) > 0), InvalidArgumentException::class, 'JSON integer exceeds the interoperable safe range.');
            }

            if ($token === '{') {
                $stack[] = ['kind' => 'object', 'keys' => [], 'expect_key' => true];
            } elseif ($token === '[') {
                $stack[] = ['kind' => 'array', 'keys' => [], 'expect_key' => false];
            } elseif ($token === '}' || $token === ']') {
                array_pop($stack);
            } elseif ($token === ',' && $stack !== []) {
                $index = array_key_last($stack);
                if ($stack[$index]['kind'] === 'object') {
                    $stack[$index]['expect_key'] = true;
                }
            } elseif (str_starts_with($token, '"') && $stack !== []) {
                $index = array_key_last($stack);
                if ($stack[$index]['kind'] === 'object' && $stack[$index]['expect_key']) {
                    $key = json_decode($token, flags: JSON_THROW_ON_ERROR);
                    throw_unless(is_string($key), InvalidArgumentException::class, 'Object key must be a string.');
                    throw_if(isset($stack[$index]['keys'][$key]), InvalidArgumentException::class, 'Duplicate JSON object key: '.$key);

                    $stack[$index]['keys'][$key] = true;
                    $stack[$index]['expect_key'] = false;
                }
            }
        }
    }
}
