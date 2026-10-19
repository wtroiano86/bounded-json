---
name: bounded-json-integration
description: Integrate or troubleshoot wtroiano/bounded-json in PHP and Laravel applications. Use when configuring its limits, resolving its services, preserving JSON object/list identity, generating canonical bytes or fingerprints, using JsonData, or redacting payloads for display and logs.
---

# Bounded JSON integration

## Locate the contract

Run `composer show wtroiano/bounded-json --path` in the consuming application to locate the installed package and check its version. If working in the package checkout itself, use that root. Paths below are relative to the package root, not this copied skill directory:

- `docs/configuration.md`: option names, defaults, ranges, construction, and container behavior.
- `docs/canonical-json.md`: accepted values, encoding rules, and hashing.
- `docs/utilities-and-errors.md`: typed access, redaction, and request-boundary examples.
- `docs/development.md`: checks when changing the package itself.

Read the sections relevant to the task. Inspect the application's existing injection, configuration, and input boundary before adding an integration.

## Select and construct the service

Use `Wtroiano\BoundedJson\Support\CanonicalJson` for raw JSON decoding, canonical encoding, and fingerprints. Use `JsonData` for strict typed access and `PayloadSerializer` for display redaction. Use the `bounded-json-schema-validation` skill when the task also needs document-shape validation.

In Laravel, constructor-inject services into actions/controllers/jobs, or resolve them for a one-off operation:

```php
use Wtroiano\BoundedJson\Support\CanonicalJson;

$json = app(CanonicalJson::class);
$document = $json->decodeObject('{"name":"Example","count":2}');
$canonical = $json->encode($document);
$fingerprint = $json->hash($document);
```

The provider is auto-discovered. Publish application-owned configuration with `php artisan vendor:publish --tag=bounded-json-config` when customization is needed. Do not edit installed vendor files to configure the consumer.

Use integer values for `bounded-json.max_bytes`, `max_json_depth`, `max_schema_depth`, `max_evaluation_depth`, `max_array_items`, and `max_string_length`. Read their distinct meanings before changing them. Set configuration before resolving the service; existing readonly instances keep their options. `new CanonicalJson` ignores Laravel configuration.

Outside Laravel, or for an intentional operation-specific policy, construct options explicitly:

```php
use Wtroiano\BoundedJson\Options\JsonLimits;
use Wtroiano\BoundedJson\Support\CanonicalJson;

$json = new CanonicalJson(new JsonLimits(maxBytes: 65536, maxDepth: 24));
$document = $json->decodeObject('{"name":"Example"}');
```

Keep fixed JSON semantics unchanged when customizing limits. Limits select accepted input; they do not alter the canonical output of an accepted value.

## Preserve the source document

1. Decode the original JSON text with `decode`, or `decodeObject` if an object root is required. For a whole Laravel request body, use the raw content; parsed request arrays cannot preserve duplicate keys or all object/list distinctions.
2. Keep exact `stdClass` objects and PHP lists throughout the value tree. Convert application DTOs explicitly. Casting an outer associative array to an object does not repair nested associative arrays.
3. Validate the business schema when required, then encode or hash the original value. `hash` accepts a PHP value: parse JSON text first, or it will hash a JSON string value.
4. Use strings for identifiers beyond the safe integer range of ±9007199254740991. Do not cast them to floats. Preserve the application's chosen representation for exact decimal values.
5. Convert rejection into the application's existing error contract. Canonical methods throw `InvalidArgumentException`; they do not create a Laravel 422 response themselves.

Canonicalization preserves list order and string contents. It does not normalize Unicode, sort business records, or authenticate a sender. Do not substitute a content digest for the application's authentication mechanism.

## Read and redact values deliberately

Use `JsonData::string`, `integer`, `boolean`, `object`, `map`, `objects`, `list`, `objectList`, and `strings` for type assertions. They throw on mismatches and do not coerce. `integer` requires a PHP integer even if a mathematically integral float passed a schema check. Check property presence or validate the schema before reading optional fields.

`JsonData::map` converts objects into PHP arrays and can lose empty object/list identity. Keep the original tree for hashing. Numeric-looking object keys can be rejected by the map helper after PHP key conversion.

Use `PayloadSerializer::redact($value)` for a display copy, or `decode($nullableJson)` to decode and redact stored text. Validate/hash before redaction. The fixed heuristic masks sensitive object property names and selected token patterns; associative array keys are not inspected. Direct `redact` does not apply canonical limits, so pass a decoded JSON tree. Add application-specific redaction outside this final class when the consumer needs more policy.

## Verify the integration

Choose checks that exercise the changed boundary:

- Verify equivalent object key orders produce identical canonical bytes/hashes and that `{}` differs from `[]`.
- Check rejection of duplicate raw keys, invalid roots, or unsupported values if that boundary accepts them.
- For a changed limit, check acceptance at the boundary and rejection just beyond it. In Laravel, resolve after setting configuration so the test covers the provider path.
- Verify a log/display path preserves the original value and does not hash the redacted copy.
- Check the application's chosen error response or job behavior for rejected documents.

Run focused application tests. When changing the package implementation, follow its `docs/development.md` and update the matching documentation and Boost resources with the public contract.
