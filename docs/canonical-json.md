# Canonical JSON

[Documentation index](../README.md#documentation)

`Wtroiano\BoundedJson\Support\CanonicalJson` turns supported PHP values into deterministic JSON bytes. The class is final and readonly. Its constructor accepts an optional `JsonLimits`, defaulting to the [package limits](configuration.md).

## Public methods

| Method | Result | Behavior |
| --- | --- | --- |
| `decode(string $json): mixed` | Decoded value | Checks input bytes, JSON syntax, duplicate keys, numeric safety, depth, and canonical output bytes. |
| `decodeObject(string $json): stdClass` | Decoded object | Performs the same checks and additionally requires an object at the root. |
| `encode(mixed $value): string` | Canonical JSON | Validates and serializes the supported PHP value tree. |
| `hash(mixed $value): string` | SHA-256 hex digest | Hashes the bytes returned by `encode`; the result is 64 hexadecimal characters. |

Methods throw `InvalidArgumentException` for invalid JSON, unsupported values, or exceeded limits. `decode` wraps a parser `JsonException` as the previous exception when syntax parsing fails. None of these methods mutate the supplied value.

## Represent JSON explicitly

| JSON value | PHP representation |
| --- | --- |
| Object | Exactly `stdClass` |
| Array | A PHP array for which `array_is_list()` is true |
| String | A valid UTF-8 string |
| Number | A supported PHP `int` or finite `float` |
| Boolean | `bool` |
| Null | `null` |

Associative arrays, sparse arrays, resources, closures, model instances, DTOs, `JsonSerializable` implementations, and subclasses of `stdClass` are not accepted by the encoder. Map such values explicitly into the supported representation first. Casting an outer array to an object does not convert nested associative arrays.

```php
use Wtroiano\BoundedJson\Support\CanonicalJson;

$json = new CanonicalJson;

$value = (object) [
    'metadata' => new stdClass,
    'tags' => ['blue', 'green'],
];

echo $json->encode($value); // {"metadata":{},"tags":["blue","green"]}
echo $json->encode([]); // []
echo $json->encode(new stdClass); // {}
```

Use `decode` on the original JSON text when duplicate-key rejection matters. If another decoder has already collapsed duplicate keys, that information cannot be recovered by `encode` or schema validation. Laravel's parsed request arrays also lose the original JSON object/list representation; use the raw body for a whole-document contract.

## Deterministic encoding rules

- Object keys are sorted by their UTF-16 big-endian representation. This is not locale-aware sorting or ordinary PHP array key sorting.
- Array order is preserved. Objects and arrays remain distinct, including `{}` and `[]`.
- Strings preserve Unicode text and do not escape Unicode characters or slashes unnecessarily. No Unicode normalization is performed: visually identical text with different code-point sequences can have different hashes.
- Duplicate object keys are rejected at every level during decoding, including escape-equivalent keys such as `"a"` and `"\u0061"`.
- Integers must be between `-9007199254740991` and `9007199254740991`, inclusive. Integer tokens are checked using their original text, so parser rounding cannot make an out-of-range token acceptable.
- Floats must be finite. Whole-valued floats outside the same safe integer range are rejected. Negative zero is encoded as `0`; `1.0` is encoded as `1`.
- Number formatting normalizes decimal and exponent forms. PHP's shortest floating-point representation is used with `serialize_precision` temporarily set to `-1`, then restored.

The implementation uses deterministic key and number formatting with a deliberately restricted numeric profile. It does not expose a selectable canonicalization standard or promise complete RFC 8785/JCS interoperability. If another implementation must reproduce hashes, test the shared value domain and representative numeric/Unicode cases across both implementations.

## Hash values, not raw input text

```php
use Wtroiano\BoundedJson\Support\CanonicalJson;

$json = new CanonicalJson;
$left = $json->decode('{"b":2,"a":1.0}');
$right = $json->decode('{ "a": 1, "b": 2 }');

assert($json->encode($left) === '{"a":1,"b":2}');
assert($json->hash($left) === $json->hash($right));
assert($json->hash(new stdClass) !== $json->hash([]));
```

`hash('{"a":1}')` hashes a JSON **string value**, including its JSON string encoding. To fingerprint the document represented by that text, call `hash(decode($text))`.

A SHA-256 digest is useful for content identity, deduplication, or change detection. It does not authenticate a sender and is not an HMAC or signature. Application rules still determine whether two documents are interchangeable: canonicalization preserves list order and string contents, and does not sort business records or normalize identifiers.

For identifiers exceeding the safe integer range, use strings in both the document and schema. For exact decimals, choose an application representation such as a decimal string; floating-point encoding does not provide arbitrary-precision arithmetic.

## Limits and validation order

`decode` checks raw bytes, parses the input, checks tokens, and passes the decoded result through `encode` to enforce the same value rules as direct encoding. A small raw document can still fail if its canonical output exceeds the configured byte limit.

Root depth is zero. `new CanonicalJson(new JsonLimits(maxDepth: 0))` can encode an empty root object or list, but cannot encode a child value. Both decoding and encoding enforce this convention. See [configuration](configuration.md#how-the-limits-interact) for the distinction between JSON, schema, and evaluation depth.

Canonicalization checks JSON representation, not a business schema. Call `BoundedSchemaValidator::validateValue` when required properties, types, or declared bounds must also be enforced.
