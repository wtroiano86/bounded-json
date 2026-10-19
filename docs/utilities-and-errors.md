# Typed access, redaction, and errors

[Documentation index](../README.md#documentation)

## Typed access with JsonData

`Wtroiano\BoundedJson\Support\JsonData` provides static assertions for reading decoded values. Every method throws `InvalidArgumentException` on a mismatch. These helpers do not coerce values or apply byte, depth, numeric-range, or schema limits.

| Method | Accepted value | Return type |
| --- | --- | --- |
| `object(mixed $value)` | A `stdClass` instance | `stdClass` |
| `string(mixed $value)` | PHP string | `string` |
| `integer(mixed $value)` | PHP integer, not a numeric string or float | `int` |
| `boolean(mixed $value)` | PHP boolean | `bool` |
| `map(mixed $value)` | `stdClass` or PHP array whose keys are all strings | `array<string, mixed>` |
| `objects(mixed $value)` | A map whose values all pass `object` | `array<string, stdClass>` |
| `list(mixed $value)` | A PHP list with contiguous keys starting at zero | `list<mixed>` |
| `objectList(mixed $value)` | A list whose values all pass `object` | `list<stdClass>` |
| `strings(mixed $value)` | A list of PHP strings | `list<string>` |

```php
use Wtroiano\BoundedJson\Support\CanonicalJson;
use Wtroiano\BoundedJson\Support\JsonData;

$document = (new CanonicalJson)->decodeObject(
    '{"name":"Example","count":2,"enabled":true,"tags":["blue"]}',
);

$name = JsonData::string($document->name);
$count = JsonData::integer($document->count);
$enabled = JsonData::boolean($document->enabled);
$tags = JsonData::strings($document->tags);
```

Check property presence or validate a schema before accessing potentially absent properties. `JsonData` accepts a value; it does not retrieve a named field or suppress PHP's missing-property behavior.

`map` is an intentional conversion for PHP access. Both `{}` and an empty PHP array yield `[]`; do not use that result to preserve JSON object identity for hashing. Numeric-looking JSON property names can become integer keys through PHP's property-array conversion and are rejected by `map`. This restriction is narrower than `CanonicalJson`, which can encode objects with numeric property names.

`object` accepts subclasses of `stdClass`, whereas `CanonicalJson` accepts only the exact class. Likewise, a PHP integer accepted here might exceed the canonical safe integer range. Use the JSON and schema APIs for those checks.

## Redacting display and log values

`Wtroiano\BoundedJson\Support\PayloadSerializer` takes a `CanonicalJson` dependency. It exposes two methods and has no `encode` method:

| Method | Behavior |
| --- | --- |
| `decode(?string $json): mixed` | Returns `null` for `null` input; otherwise decodes with `CanonicalJson`, then redacts the decoded value. |
| `redact(mixed $value): mixed` | Recursively produces a redacted copy of a JSON-shaped value. |

```php
use Wtroiano\BoundedJson\Support\CanonicalJson;
use Wtroiano\BoundedJson\Support\PayloadSerializer;

$json = new CanonicalJson;
$serializer = new PayloadSerializer($json);

$original = $json->decodeObject(
    '{"apiKey":"example-key","message":"Authorization: Bearer abc123","count":2}',
);

$fingerprint = $json->hash($original);
$display = $serializer->redact($original);

echo $json->encode($display);
// {"apiKey":"[redacted]","count":2,"message":"Authorization: [redacted]"}

assert($original->apiKey === 'example-key');
```

For a nullable stored JSON column, `decode($storedJson)` combines decoding and redaction. Use the ordinary JSON decoder when the caller needs the original value for validation, execution, or hashing.

### Redaction rules

- Object property names are normalized for case and camel-case boundaries. Matching name segments include `password`, `secret`, `token`, `authorization`, `cookie`, `credential`, their supported plurals, `apiKey`, and `privateKey`, including underscore/hyphen forms. A matching property value is replaced in full with `[redacted]`.
- String contents mask case-insensitive `Bearer` followed by whitespace and a non-whitespace token. They also mask lowercase `sk-` followed by at least 16 ASCII letters, digits, underscores, or hyphens.
- Objects and arrays are traversed recursively. New `stdClass` objects and arrays are produced; the original decoded tree is unchanged.
- PHP associative array **keys are not inspected** for sensitive names. Only their values are recursively processed. Use `stdClass` for JSON objects.
- Other values pass through. Arbitrary object classes are not inspected. Direct `redact` does not first canonicalize its input or apply the JSON depth/byte limits; use it on a decoded JSON tree, not arbitrary cyclic structures.

These rules and the mask are fixed, with no configuration hooks. They are a heuristic for display and logs, not a guarantee that every secret is removed. Keep application-specific redaction policy in the application. Validate and fingerprint the original document, then redact a separate copy for its intended audience.

## Error handling

| Operation | Exceptions to account for |
| --- | --- |
| Construct limits | `InvalidArgumentException` for invalid ranges; PHP `TypeError` for invalid argument types in strict calls. |
| Resolve Laravel options | `InvalidArgumentException` for non-integer configuration or invalid ranges. |
| Canonical encode/decode/hash | `InvalidArgumentException` for invalid or unsupported JSON values and exceeded limits. |
| `JsonData` assertions | `InvalidArgumentException`. |
| Bounded definition validation | `InvalidArgumentException` for definition/canonical checks; enum entries checked by the underlying evaluator can raise `RuntimeException`. |
| Bounded runtime value validation | `JsonValueInvalid` for evaluator failures; canonicalization and canonical enum identity checks can raise `InvalidArgumentException`. |
| Broader schema evaluation | `RuntimeException` for validation and supported-structure checks; malformed schemas can also cause PHP errors. |
| `PayloadSerializer::decode` | Propagates canonical decoding exceptions. |

`Wtroiano\BoundedJson\Exceptions\JsonValueInvalid` extends `RuntimeException`. Its message is `Value does not satisfy the bounded schema.` and its previous exception contains the evaluator failure. Do not assume every bounded validation rejection uses this class. In particular, canonical enum identity checks can reject a value with `InvalidArgumentException` after the broader evaluator considers it equal.

Exceptions do not contain a structured collection of field errors or stable JSON-pointer paths. Do not parse exception messages as an API. The package does not automatically turn a failure into an HTTP 422 response or choose a queue retry policy.

### Adapting a Laravel request boundary

Validate application-owned schemas independently from user input so schema/configuration errors are visible as application problems. The following controller uses a trusted, fixed definition, then converts document rejection into a Laravel validation response:

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Wtroiano\BoundedJson\Exceptions\JsonValueInvalid;
use Wtroiano\BoundedJson\Support\BoundedSchemaValidator;
use Wtroiano\BoundedJson\Support\CanonicalJson;

final readonly class FingerprintDocument
{
    public function __construct(
        private CanonicalJson $json,
        private BoundedSchemaValidator $validator,
    ) {}

    public function __invoke(Request $request): array
    {
        $schema = $this->json->decodeObject(
            '{"type":"object","properties":{"name":{"type":"string","maxLength":80}},"required":["name"],"additionalProperties":false}',
        );
        $this->validator->validateDefinition($schema);

        try {
            $value = $this->json->decodeObject($request->getContent());
            $this->validator->validateRuntimeValue($schema, $value);
        } catch (InvalidArgumentException|JsonValueInvalid) {
            throw ValidationException::withMessages([
                'document' => 'The JSON document does not match the required shape or limits.',
            ]);
        }

        return ['fingerprint' => $this->json->hash($value)];
    }
}
```

This example validates a whole raw JSON body. Keep ordinary Form Request rules for other application inputs. For user-authored schema definitions, handle definition rejection at the schema-submission boundary and only persist accepted schemas; validate them again when the applicable policy changes.
