# Configuration

[Documentation index](../README.md#documentation)

Limits control which documents and schemas are accepted. Changing a limit does not change the canonical bytes or hash of a value accepted under both configurations. JSON identity, duplicate-key rejection, numeric rules, the bounded schema keyword set, and redaction patterns are fixed behavior.

## Laravel configuration

The auto-discovered provider merges package defaults into the `bounded-json` configuration namespace. Publishing is optional unless the application wants its own configuration file:

```bash
php artisan vendor:publish --tag=bounded-json-config
```

The published `config/bounded-json.php` contains:

```php
<?php

declare(strict_types=1);

return [
    'max_bytes' => 262144,
    'max_json_depth' => 64,
    'max_schema_depth' => 16,
    'max_evaluation_depth' => 32,
    'max_array_items' => 10000,
    'max_string_length' => 10000,
];
```

| Configuration key | Option property | Default | Meaning and valid range |
| --- | --- | --- | --- |
| `max_bytes` | `JsonLimits::maxBytes` | 262144 | Positive integer; maximum bytes of the raw JSON input and of the canonical output, checked separately. |
| `max_json_depth` | `JsonLimits::maxDepth` | 64 | Integer from 0 through 2147483645; maximum JSON value depth, with the root at zero. |
| `max_schema_depth` | `SchemaLimits::maxDepth` | 16 | Nonnegative integer; maximum bounded schema nesting through `properties` and `items`, with the root at zero. |
| `max_evaluation_depth` | `SchemaLimits::maxEvaluationDepth` | 32 | Nonnegative integer; maximum recursive depth while inspecting and evaluating schemas. References, alternatives, and child values consume levels. |
| `max_array_items` | `SchemaLimits::maxArrayItems` | 10000 | Nonnegative integer; ceiling on a bounded schema's declared `minItems` and `maxItems`. |
| `max_string_length` | `SchemaLimits::maxStringLength` | 10000 | Nonnegative integer; ceiling on a bounded schema's declared `minLength` and `maxLength`. |

All six Laravel configuration values must be PHP integers. Numeric strings, floats, booleans, and `null` are rejected when the corresponding options are resolved. Unknown keys do not introduce new policies. The package does not define environment variable names or an unlimited mode.

String lengths use `mb_strlen`, so keep `mb_internal_encoding()` set to UTF-8 when counting JSON text. Length is a character count, not a byte count or a count of visible grapheme clusters. A schema with `maxLength: 100` permits fewer characters than one with `maxLength: 1000`, even when the configured ceiling is 10000.

If the application uses environment variables in its published configuration, convert them to integers there and validate deployment values. Rebuild Laravel's configuration cache through the application's normal deployment process after changes.

## Resolve services through the container

Constructor injection is the usual Laravel integration:

```php
use Wtroiano\BoundedJson\Support\CanonicalJson;

final readonly class DocumentFingerprint
{
    public function __construct(private CanonicalJson $json) {}

    public function fromJson(string $document): string
    {
        return $this->json->hash($this->json->decode($document));
    }
}
```

`BoundedSchemaValidator`, `SchemaValueValidator`, and `PayloadSerializer` can also be injected. The provider binds `JsonLimits` and `SchemaLimits`; Laravel constructs the services using those options. Both option classes are immutable.

The bindings are transient. Each new resolution reads the current configuration. Already constructed services retain their options, including services held by application singletons or long-running workers. In tests, set configuration before resolving the subject:

```php
use Wtroiano\BoundedJson\Support\CanonicalJson;

config(['bounded-json.max_bytes' => 1024]);
$json = app(CanonicalJson::class);
```

Calling `new CanonicalJson` or `new SchemaValueValidator` bypasses application configuration and uses default options. Use explicit construction only when a separate policy is intentional.

If package discovery is disabled, add `Wtroiano\BoundedJson\Providers\BoundedJsonServiceProvider::class` to the application's `bootstrap/providers.php`. Do not register it twice when discovery is enabled.

## Standalone or operation-specific options

Pass options explicitly outside Laravel, or when one operation requires its own limits. Use the same `SchemaLimits` instance for the two schema validators:

```php
use Wtroiano\BoundedJson\Options\JsonLimits;
use Wtroiano\BoundedJson\Options\SchemaLimits;
use Wtroiano\BoundedJson\Support\BoundedSchemaValidator;
use Wtroiano\BoundedJson\Support\CanonicalJson;
use Wtroiano\BoundedJson\Support\SchemaValueValidator;

$jsonLimits = new JsonLimits(maxBytes: 65536, maxDepth: 24);
$schemaLimits = new SchemaLimits(
    maxDepth: 8,
    maxEvaluationDepth: 24,
    maxArrayItems: 250,
    maxStringLength: 2000,
);

$json = new CanonicalJson($jsonLimits);
$valueValidator = new SchemaValueValidator($schemaLimits);
$validator = new BoundedSchemaValidator($json, $valueValidator, $schemaLimits);
```

The constructors reject out-of-range integers with `InvalidArgumentException`; strict PHP calls with the wrong argument types raise `TypeError`. There are no setters or per-call overrides. Construct a new instance for a different policy.

## How the limits interact

- `max_bytes` applies to input before decoding and output after canonical serialization. Whitespace counts toward input size. It also applies when a bounded schema definition or runtime value is canonicalized.
- JSON depth measures the value tree. At depth zero, scalars and empty root containers fit; `[1]` and `{"a":1}` do not. Cycles eventually exceed the depth limit.
- Bounded schema depth counts child schemas. A root object with a property schema needs depth one. The schema's JSON representation contains additional layers such as `properties`, so its JSON depth is a separate constraint.
- Evaluation depth applies to `SchemaValueValidator` both directly and inside `BoundedSchemaValidator`. Even a definition accepted by the schema-depth policy may exceed the evaluation-depth policy. There is no automatic relationship between the settings.
- Zero is valid for all limits except bytes. An array/string ceiling of zero permits only zero declared bounds. A schema-depth ceiling of zero permits only schemas without nested child schemas.
- `max_array_items` and `max_string_length` constrain **bounded schema declarations**. `CanonicalJson` does not independently count list items or string characters. The broader `SchemaValueValidator` uses only `maxEvaluationDepth` from `SchemaLimits`; it does not enforce the other three settings.

Limits are rejection checks, not a streaming parser or a complete memory/CPU budget. In particular, canonical output is built before its byte size is checked, and broader schema alternatives or uniqueness checks can require substantial work within the depth limit.
