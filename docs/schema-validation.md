# Schema validation

[Documentation index](../README.md#documentation)

The package has two validation entry points with different contracts. Both return `void` on success and throw on failure. Neither coerces values, removes extra fields, inserts defaults, or returns a transformed document.

| Requirement | Use |
| --- | --- |
| Strict document shape, explicit array/string bounds, canonical JSON values | `BoundedSchemaValidator` |
| Local references, alternative schemas, patterns, or other supported subset keywords | `SchemaValueValidator` |
| Complete JSON Schema draft support or full metaschema validation | A separate implementation that provides that contract |

## Bounded schemas

`Wtroiano\BoundedJson\Support\BoundedSchemaValidator` takes a `CanonicalJson`, a `SchemaValueValidator`, and an optional `SchemaLimits`. Laravel resolves all three through the container. For standalone construction with custom limits, follow the [shared-options example](configuration.md#standalone-or-operation-specific-options).

| Method | Contract |
| --- | --- |
| `validateDefinition(mixed $schema): void` | Canonicalize and check a bounded schema definition, including its enum entries. The definition must be a `stdClass`. |
| `validateValue(stdClass $schema, mixed $value): void` | Validate the definition, then validate the value. This is the normal entry point. |
| `validateRuntimeValue(stdClass $schema, mixed $value): void` | Validate a value against an already validated bounded schema. Skips the bounded definition check, but still canonicalizes the value and runs the underlying evaluator. |

Use `validateRuntimeValue` only with an unchanged schema previously accepted by `validateDefinition` under the applicable options. Revalidate definitions after changes or after changing the acceptance policy. This method does not cache or compile schemas, and the underlying evaluator still traverses their structure.

### Complete example

The following standalone example defines required fields, an optional nullable field, and a bounded list. In Laravel, inject or resolve `$json` and `$validator` instead of constructing them directly.

```php
use Wtroiano\BoundedJson\Support\BoundedSchemaValidator;
use Wtroiano\BoundedJson\Support\CanonicalJson;
use Wtroiano\BoundedJson\Support\SchemaValueValidator;

$json = new CanonicalJson;
$validator = new BoundedSchemaValidator($json, new SchemaValueValidator);

$schema = $json->decodeObject(<<<'JSON'
{
    "type": "object",
    "properties": {
        "name": { "type": "string", "minLength": 1, "maxLength": 80 },
        "note": { "type": ["string", "null"], "maxLength": 200 },
        "tags": {
            "type": "array",
            "items": { "type": "string", "maxLength": 20 },
            "maxItems": 5
        },
        "status": {
            "type": "string",
            "maxLength": 8,
            "enum": ["draft", "ready"]
        }
    },
    "required": ["name", "tags", "status"],
    "additionalProperties": false
}
JSON);

$value = $json->decodeObject(<<<'JSON'
{"name":"Report","note":null,"tags":["finance"],"status":"draft"}
JSON);

$validator->validateValue($schema, $value);
$canonical = $json->encode($value);
```

`note` can be absent because it is not in `required`. It can be `null` because its type includes `null`. Presence and nullability are independent.

### Definition rules

The complete bounded keyword set is:

```text
type properties required additionalProperties items
minItems maxItems minLength maxLength minimum maximum
enum title description
```

| Area | Rules |
| --- | --- |
| Every schema | Must be an object and declare `type`. Unknown keywords are rejected. |
| Types | `object`, `array`, `string`, `number`, `integer`, `boolean`, or `null`. Use a single type string, a one-element list, or one non-null type plus `null`. Duplicate types and other unions are rejected. |
| Object | `properties` must be an object, even when empty. `additionalProperties` must be `false`. Every child property must be a bounded schema. |
| Required fields | Optional unique list of strings. Each name must appear in `properties`. Declared properties are optional unless listed. |
| Array | Requires an `items` schema and integer `maxItems`. Optional integer `minItems`. Both must be nonnegative and at most `maxArrayItems`, with minimum no greater than maximum. |
| String | Requires integer `maxLength`. Optional integer `minLength`. Both must be nonnegative and at most `maxStringLength`, with minimum no greater than maximum. |
| Number/integer | Optional finite numeric `minimum` and `maximum`, ordered when both are present. Canonical numeric restrictions also apply. |
| Enum | Nonempty list of canonically distinct values. Each entry must satisfy the rest of the schema, including nested enums. |
| Annotations | Optional string `title` and `description`; they do not affect value validation. |

Type-specific keywords are rejected on other types. For example, `maxLength` is invalid on an integer schema. For nullable schemas, the non-null type determines the required keywords, so a nullable string still needs `maxLength`.

`integer` uses mathematical integrality: a value such as the float `1.0` satisfies an integer schema. `JsonData::integer` is stricter and accepts only PHP integers. See [typed access](utilities-and-errors.md#typed-access-with-jsondata).

Bounded enums use canonical equality. `1` and `1.0` are duplicate enum entries; `{}` and `[]` are distinct, including when nested in enum values. Strings are compared without Unicode normalization.

### What bounded schemas exclude

Bounded definitions do not accept `$schema`, `$id`, `$ref`, `$defs`, `definitions`, `const`, `pattern`, `format`, `uniqueItems`, `minProperties`, `maxProperties`, `multipleOf`, exclusive numeric bounds, combinators, conditional schemas, or boolean schemas. They also do not accept arbitrary keyword extensions. Adding a configured limit does not enable these features.

If a document needs a property named `$ref`, that name can appear inside `properties`; it is a data property name, not a schema keyword at that location.

## Broader schema subset

`Wtroiano\BoundedJson\Support\SchemaValueValidator` accepts an optional `SchemaLimits` and exposes:

```text
validate(array $schema, mixed $value): void
```

The **schema tree uses PHP arrays**, while JSON object values should still be `stdClass`. This differs from the bounded validator's schema input. Nested boolean schemas are supported in positions such as `items` and `additionalProperties`; the public root parameter must be an array. An empty array at the root represents an unconstrained schema.

```php
use Wtroiano\BoundedJson\Support\CanonicalJson;
use Wtroiano\BoundedJson\Support\SchemaValueValidator;

$json = new CanonicalJson;
$validator = new SchemaValueValidator;

$schema = [
    '$defs' => [
        'code' => [
            'type' => 'string',
            'maxLength' => 12,
            'pattern' => '^[A-Z][A-Z0-9]*$',
        ],
    ],
    'type' => 'object',
    'properties' => [
        'code' => ['$ref' => '#/$defs/code'],
        'quantity' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
    ],
    'required' => ['code', 'quantity'],
    'additionalProperties' => false,
];

$value = $json->decodeObject('{"code":"ITEM42","quantity":3}');
$validator->validate($schema, $value);
```

Using `CanonicalJson` first adds JSON representation, byte, depth, and numeric checks. Calling `SchemaValueValidator` directly does not add those protections.

### Supported keywords

| Group | Keywords and behavior |
| --- | --- |
| Types | `type`; supports the listed JSON types and lists of alternatives. |
| Objects | `properties`, `required`, `additionalProperties`, `minProperties`, `maxProperties`. Additional properties are allowed by default; `additionalProperties` may be a boolean or a schema. |
| Arrays | `items`, `minItems`, `maxItems`, `uniqueItems`. One `items` schema applies to each item; tuple/prefix item schemas are unsupported. |
| Strings | `minLength`, `maxLength`, `pattern`. Patterns use PHP PCRE in Unicode mode; add anchors for whole-string matching. |
| Numbers | `minimum`, `maximum`, numeric `exclusiveMinimum`, numeric `exclusiveMaximum`, `multipleOf`. |
| Equality | `enum`, `const`. See the object/array limitation below. |
| Composition | `allOf`, `anyOf`, `oneOf`, `not`. All, at least one, exactly one, or no matching child schema, respectively. |
| References | `$ref`, `$defs`, `definitions`. References must be root-local JSON pointers beginning with `#/`. Pointer segments decode `~1` as `/` and `~0` as `~`. |
| Annotations | `$schema`, `$id`, `title`, `description`, `default`, `examples`, `deprecated`, `readOnly`, `writeOnly`. Accepted without applying defaults, selecting a draft, resolving a base URI, or enforcing access rules. |

Only these keywords are accepted. There is no remote schema loading, bare `#` reference, named anchor resolution, `format`, `patternProperties`, conditional validation, or unevaluated-property support. Sibling keywords alongside `$ref` are also evaluated.

### Limits and differences

This is a small evaluator, not a full JSON Schema metaschema validator. It checks supported structure and evaluates values, but it does not comprehensively validate every keyword's own type or every draft-specific rule. Supply well-formed schemas within this documented subset. Malformed PHP schemas can produce PHP errors in addition to validation exceptions.

Only `SchemaLimits::maxEvaluationDepth` applies directly. Upper array/string bounds are optional, and the bounded schema ceilings are not enforced. Local reference cycles eventually exceed the evaluation depth; recursive schema support is not unbounded. Nested `true` schemas can stop traversal, so evaluation depth is not a substitute for limiting the input value tree with `CanonicalJson`.

Other differences matter when choosing the entry point:

- Direct type checks accept arbitrary PHP objects as `object` and PHP arrays as `array`. Canonicalize input first when exact JSON representation matters.
- Equality for `enum`, `const`, and `uniqueItems` converts objects to PHP property arrays. It can conflate objects and arrays, including `{}` and `[]`, and numeric-key objects with lists. Use bounded validation for canonical enum identity.
- `multipleOf` uses floating-point arithmetic with a tolerance of `1e-9` on the quotient. It is not exact decimal arithmetic.
- Patterns follow PHP PCRE behavior, not a promise of JSON Schema's regular-expression dialect. Pattern evaluation and schema branching can be expensive; depth alone does not bound total work. `uniqueItems` compares pairs of entries.

Validation failures and unsupported structures generally raise `RuntimeException`. The bounded wrapper translates evaluator failures into `JsonValueInvalid`, while some canonical and enum checks raise `InvalidArgumentException`. See [error handling](utilities-and-errors.md#error-handling) before integrating with HTTP or job boundaries.
