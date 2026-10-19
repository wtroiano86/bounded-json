---
name: bounded-json-schema-validation
description: Author, integrate, and debug schemas using wtroiano/bounded-json. Use when working with BoundedSchemaValidator or SchemaValueValidator, defining bounded strings and arrays, handling nullability and required fields, selecting supported schema keywords, or testing schema and runtime value rejection.
---

# Bounded JSON schema validation

## Locate the schema contract

Run `composer show wtroiano/bounded-json --path` in the consuming application, or use the current package checkout. Read `docs/schema-validation.md` at that package root for supported keywords and differences between validators. Read `docs/configuration.md` for limits and `docs/utilities-and-errors.md` for exception handling. These paths do not refer to this installed skill folder.

Inspect existing schema storage, validation calls, and application limits. Keep application-owned definitions and error policy in the consumer.

## Choose the entry point

- Use `Wtroiano\BoundedJson\Support\BoundedSchemaValidator` for a strict bounded document contract. Its schema tree is `stdClass`; values use canonical JSON representations.
- Use `Wtroiano\BoundedJson\Support\SchemaValueValidator` only when the task needs its broader documented subset, such as local references, alternatives, or patterns. Its schema tree uses PHP arrays. It does not enforce bounded array/string ceilings or canonical input rules.
- Do not promise a complete JSON Schema draft. If the requested feature is unsupported, identify the requirement and use an implementation that supplies it rather than silently dropping the keyword or changing validation semantics.

Resolve services through Laravel's container so configured options apply. For standalone use, construct `CanonicalJson`, one `SchemaLimits`, and `SchemaValueValidator($limits)`, then pass the JSON service, value validator, and same limits to `BoundedSchemaValidator`.

## Build a bounded definition

Every node requires `type`: a supported JSON type, a one-element type list, or one non-null type plus `null`. Objects require an object-valued `properties` and `additionalProperties: false`. Arrays require an `items` schema and `maxItems`. Strings require `maxLength`. Required properties are a separate unique list of declared names.

```php
use Wtroiano\BoundedJson\Support\BoundedSchemaValidator;
use Wtroiano\BoundedJson\Support\CanonicalJson;

$json = app(CanonicalJson::class);
$validator = app(BoundedSchemaValidator::class);

$schema = $json->decodeObject(<<<'JSON'
{
    "type": "object",
    "properties": {
        "title": { "type": "string", "minLength": 1, "maxLength": 80 },
        "note": { "type": ["string", "null"], "maxLength": 200 },
        "tags": {
            "type": "array",
            "items": { "type": "string", "maxLength": 20 },
            "maxItems": 5
        }
    },
    "required": ["title", "tags"],
    "additionalProperties": false
}
JSON);

$value = $json->decodeObject('{"title":"Report","tags":[],"note":null}');
$validator->validateValue($schema, $value);
```

Keep declared item/length bounds within configured ceilings and minimums no greater than maximums. String length counts Unicode characters with UTF-8 mbstring settings. Numeric minimum/maximum bounds are optional. A nullable string or array still needs the bounds required by its non-null type.

The bounded keyword set is exactly `type`, `properties`, `required`, `additionalProperties`, `items`, `minItems`, `maxItems`, `minLength`, `maxLength`, `minimum`, `maximum`, `enum`, `title`, and `description`. Do not add `$schema`, `$ref`, `format`, `pattern`, or combinators to a bounded definition.

Use nonempty enums whose entries satisfy the rest of the schema. Canonical equality makes `1` and `1.0` duplicate entries but keeps `{}` and `[]` distinct, including nested values.

## Separate definition and value failures

Use `validateValue($schema, $value)` for normal calls. Use `validateDefinition` at definition ingestion or a trusted setup boundary when storing reusable schemas. Call `validateRuntimeValue` only after the unchanged definition was accepted under the applicable limits; it skips bounded definition checks and is not a compiled-schema cache.

Canonical input errors and some enum identity rejections raise `InvalidArgumentException`. Evaluator failures during bounded runtime validation raise `Wtroiano\BoundedJson\Exceptions\JsonValueInvalid`, a `RuntimeException` subclass with the original evaluator exception attached. Definition enum checks can themselves raise `RuntimeException`.

Do not catch only `JsonValueInvalid` and assume every rejection is handled. Keep failures in application-owned definitions/configuration distinct from malformed user documents. Avoid converting programming errors into a generic successful or empty value.

## Use the broader subset precisely

Read the keyword table before constructing a broader schema. Keep the root and schema children as PHP arrays, with booleans only in supported nested schema positions. Keep JSON object values as `stdClass`; decode raw values with `CanonicalJson` first when its safety rules are required.

Local references must begin with `#/`, for example `#/$defs/code`. Remote URLs, named anchors, and bare `#` are unsupported. Recursive references consume evaluation depth. Annotation keywords do not apply defaults, select a draft, or enforce read/write access.

Only `maxEvaluationDepth` applies from `SchemaLimits`. The evaluator does not require upper string/array bounds or use `maxArrayItems`/`maxStringLength`. Its `enum`, `const`, and `uniqueItems` comparisons can conflate object and array values. Patterns use PHP PCRE, and `multipleOf` uses floating-point tolerance. These are contract differences, not options to enable or disable.

## Verify the changed contract

Test schema acceptance separately from value acceptance. Select cases relevant to the change:

- Required property missing, optional property absent, and nullable property explicitly null.
- Extra property rejected and wrong JSON type rejected.
- String/list exactly at the declared bound and one beyond it.
- Invalid definition missing `maxLength`, `maxItems`, or closed-object settings.
- Enum identity or canonical numeric behavior when enums/numbers are involved.
- JSON, schema, and evaluation depth independently when adjusting nesting.
- Unsupported keyword or unresolved local reference when using the broader subset.

Use actual decoded JSON fixtures for object/list distinctions. Assert exception types and externally visible results; do not parse messages as structured validation paths. Run focused consumer tests, or the package's documented checks when modifying the validators themselves.
