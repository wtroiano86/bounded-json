# Bounded JSON

`wtroiano/bounded-json` provides canonical JSON, bounded schemas, a broader schema subset evaluator, typed access, and display redaction. Support classes live in `Wtroiano\BoundedJson\Support`; immutable options live in `Wtroiano\BoundedJson\Options`.

## Integration conventions

- Inject or resolve `CanonicalJson`, `BoundedSchemaValidator`, `SchemaValueValidator`, and `PayloadSerializer` in Laravel. The auto-discovered provider supplies options from `config('bounded-json.*')`. Direct `new` construction uses defaults unless explicit options are supplied.
- Publish configuration with `php artisan vendor:publish --tag=bounded-json-config`. Supported integer keys are `max_bytes`, `max_json_depth`, `max_schema_depth`, `max_evaluation_depth`, `max_array_items`, and `max_string_length`. Set configuration before resolving services; existing instances retain their options.
- Preserve JSON objects as exact `stdClass` instances and arrays as PHP lists. `CanonicalJson` rejects associative arrays, arbitrary objects, duplicate input keys, unsafe integers, and non-finite numbers. Decode raw JSON before hashing or validating when the original document matters.

@verbatim
```php
use Wtroiano\BoundedJson\Support\CanonicalJson;

$json = app(CanonicalJson::class);
$value = $json->decodeObject('{"name":"Example","enabled":true}');
$canonical = $json->encode($value);
$fingerprint = $json->hash($value);
```
@endverbatim

## Choose the validation contract

- `BoundedSchemaValidator::validateValue($schema, $value)` is the normal bounded entry point. Its schema is a `stdClass`; each node needs a type, objects need `properties` and `additionalProperties: false`, arrays need `items` and `maxItems`, and strings need `maxLength`. Only one type or one type plus null is permitted. Required fields and nullability are separate.
- `validateRuntimeValue` skips bounded definition checks. Use it only after `validateDefinition` accepts the unchanged schema under the applicable limits.
- `SchemaValueValidator::validate($schema, $value)` takes a PHP array schema tree and implements a broader subset with local references and alternatives. It uses only the evaluation-depth option; it is not a full JSON Schema implementation or a replacement for canonical input checks. Its enum/const/uniqueness equality can conflate objects and arrays.
- Catch `Wtroiano\BoundedJson\Exceptions\JsonValueInvalid` and `InvalidArgumentException` as appropriate at the application input boundary. Definition failures may also raise `RuntimeException`. Keep application schema/configuration failures separate from invalid user documents; there is no automatic Laravel validation response.

## Preserve document meaning

Use `JsonData` for strict typed access without coercion. Use `PayloadSerializer` on a separate display/log copy after validation and hashing; its fixed redaction rules inspect object keys and string patterns, not associative array keys. Redaction is heuristic.

Activate `bounded-json-integration` for configuration, canonicalization, hashing, or redaction tasks. Activate `bounded-json-schema-validation` for schema authoring and validation tasks. Locate the installed package with `composer show wtroiano/bounded-json --path`; its `README.md` and `docs/` contain the full contract. Keep consumer policies in the application.
