# Bounded JSON

`wtroiano/bounded-json` provides deterministic JSON encoding and hashing, a strict schema format with explicit size bounds, and helpers for reading and redacting JSON values. It works in ordinary PHP code and integrates with Laravel's container and configuration.

## Why use this package?

Use Bounded JSON when a document needs predictable encoding and consistent acceptance rules wherever your application processes it.

- **Stable content fingerprints.** `{"b":2,"a":1}` and `{"a":1,"b":2}` produce the same canonical bytes and hash. Use those fingerprints for content-based cache keys, deduplication, or detecting changes to stored definitions.
- **Explicit document limits.** Set byte and nesting limits, then define closed object shapes with required fields and maximum string lengths and list sizes. Reject documents that exceed the contract before passing them into application logic.
- **Preserved JSON meaning.** Keep objects and arrays distinct, including `{}` and `[]`. Reject duplicate object keys, unsafe integers, and non-finite numbers so downstream code can rely on the accepted representation.
- **One contract across entry points.** Apply the same validation to API documents, queued payloads, stored configuration, and structured AI or tool outputs. Customize limits through Laravel configuration or explicit PHP options.

Laravel already provides request validation, JSON responses, and model JSON casts. Bounded JSON adds deterministic encoding and a reusable bounded document schema alongside those features. For ordinary form fields or JSON responses, Laravel's built-in tools may be sufficient; this package is useful when the document's precise representation, fingerprint, and acceptance limits are part of your application's contract.

The package has no dependency on a particular application's domain, and its core APIs work outside a full Laravel application.

## What the package delivers

| Component | Purpose |
| --- | --- |
| `CanonicalJson` | Decode JSON, reject duplicate keys and unsupported values, produce deterministic bytes, and compute SHA-256 hashes. |
| `BoundedSchemaValidator` | Check strict schema definitions and validate values against them, with explicit array and string bounds. |
| `SchemaValueValidator` | Evaluate a broader JSON Schema subset, including local references and alternatives. |
| `JsonData` | Assert PHP types when reading decoded JSON, without coercion. |
| `PayloadSerializer` | Decode and redact JSON for display or logging. |
| `JsonLimits` / `SchemaLimits` | Customize size and depth policies with immutable options. |

All support classes use the `Wtroiano\BoundedJson\Support` namespace. Options use `Wtroiano\BoundedJson\Options`.

## Requirements and installation

- PHP 8.5 within the PHP 8.x series.
- The `mbstring` extension, with UTF-8 as the internal encoding for character-length checks.
- Illuminate Support 13.34 or later within the 13.x series. A complete Laravel application is optional; standalone installations still install this dependency.

In a project whose Composer repositories make the package available:

```bash
composer require wtroiano/bounded-json
```

For a local checkout, first add a Composer path repository to the consuming application's `composer.json`, using the actual relative path:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../bounded-json",
            "options": { "symlink": true }
        }
    ]
}
```

Merge this entry with existing repositories. For an untagged local checkout, allow its development version explicitly:

```bash
composer require 'wtroiano/bounded-json:*@dev'
```

Tagged releases use their Git version. These examples do not assume the package has been published to a public registry.

Once a release is tagged on [GitHub](https://github.com/wtroiano86/bounded-json), you can install it before Packagist registration by adding the VCS repository:

```bash
composer config repositories.bounded-json vcs https://github.com/wtroiano86/bounded-json
composer require 'wtroiano/bounded-json:^0.1.0'
```

## Standalone quick start

```php
<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use Wtroiano\BoundedJson\Support\BoundedSchemaValidator;
use Wtroiano\BoundedJson\Support\CanonicalJson;
use Wtroiano\BoundedJson\Support\SchemaValueValidator;

$json = new CanonicalJson;
$validator = new BoundedSchemaValidator($json, new SchemaValueValidator);

$schema = $json->decodeObject(<<<'JSON'
{
    "type": "object",
    "properties": {
        "name": { "type": "string", "maxLength": 80 },
        "enabled": { "type": "boolean" }
    },
    "required": ["name", "enabled"],
    "additionalProperties": false
}
JSON);

$value = $json->decodeObject('{"name":"Example","enabled":true}');
$validator->validateValue($schema, $value);

echo $json->encode($value); // {"enabled":true,"name":"Example"}
$fingerprint = $json->hash($value); // 64-character SHA-256 hex digest
```

JSON objects are `stdClass`; JSON arrays are PHP lists. Preserve that distinction throughout decoding, validation, and hashing. Associative PHP arrays are not accepted by `CanonicalJson` as JSON objects.

## Laravel quick start

Laravel discovers `BoundedJsonServiceProvider` through Composer. Inject `CanonicalJson`, `BoundedSchemaValidator`, or the other services into application classes, or resolve them for a one-off operation:

```php
use Wtroiano\BoundedJson\Support\CanonicalJson;

$json = app(CanonicalJson::class);
$value = $json->decodeObject('{"count":3}');
$canonical = $json->encode($value);
```

To customize the limits, publish the configuration:

```bash
php artisan vendor:publish --tag=bounded-json-config
```

Edit `config/bounded-json.php`. Container-resolved services use this configuration; direct construction such as `new CanonicalJson` uses the class defaults unless you pass options. The package registers no routes, migrations, or facade.

## Defaults and boundaries

The defaults allow 262,144 JSON bytes, JSON depth 64, bounded schema depth 16, evaluation depth 32, and schema-declared array/string bounds up to 10,000. These limits have different meanings; see [configuration](docs/configuration.md) before changing them.

Canonicalization uses a restricted numeric range and deterministic key/number formatting. It is not a claim of complete RFC 8785 conformance. The schema validators implement documented subsets, not a complete JSON Schema draft. Redaction is heuristic and should be applied to a separate display or log copy.

## Documentation

- [Configuration and dependency injection](docs/configuration.md)
- [Canonical JSON API and hashing](docs/canonical-json.md)
- [Bounded schemas and the broader schema validator](docs/schema-validation.md)
- [Typed access, redaction, and error handling](docs/utilities-and-errors.md)
- [Laravel Boost guidelines and agent skills](docs/agentic-development.md)
- [Package development and verification](docs/development.md)
- [Release preparation and publishing](docs/releasing.md)
- [Changelog](CHANGELOG.md)

The package ships Boost resources under `resources/boost`. Boost is optional development tooling in the consuming application and is not a runtime dependency.

## License

Released under the [MIT license](LICENSE).
