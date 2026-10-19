# Package development

[Documentation index](../README.md#documentation)

## Local setup

Use the [supported PHP version and extensions](../README.md#requirements-and-installation), then install development dependencies from the package root:

```bash
composer install
```

The package tests run directly with Pest. A Laravel application and database are not required for its unit suite. To exercise Laravel auto-discovery or Boost installation, use a separate Laravel application with a Composer path repository pointing to this checkout.

## Source map

| Path | Responsibility |
| --- | --- |
| `src/Support/CanonicalJson.php` | Parsing checks, canonical encoding, and hashing. |
| `src/Support/BoundedSchemaValidator.php` | Bounded definition rules and canonical enum identity. |
| `src/Support/SchemaValueValidator.php` | Broader schema subset evaluation. |
| `src/Support/JsonData.php` | Strict typed access helpers. |
| `src/Support/PayloadSerializer.php` | Display/log redaction. |
| `src/Options/` | Immutable, validated numeric policies. |
| `src/Providers/BoundedJsonServiceProvider.php` | Laravel configuration merge, option bindings, and publishing. |
| `src/Exceptions/JsonValueInvalid.php` | Runtime value rejection exception. |
| `config/bounded-json.php` | Publishable Laravel defaults. |
| `tests/Unit/` | Behavior tests, including provider configuration. |
| `resources/boost/` | Shipped agent guidelines and skills. |

## Verification commands

| Command | Check |
| --- | --- |
| `composer test:unit` | Pest tests. |
| `composer test:metadata` | Strict Composer metadata validation. |
| `composer test:release -- v0.1.0` | Check the planned stable tag against a dated changelog entry and package metadata; run after preparing the release date. |
| `composer test:lint` | Pint formatting check. |
| `composer lint` | Apply Pint formatting. |
| `composer test:types` | PHPStan at the configured maximum level. |
| `composer test:type-coverage` | Pest type coverage, minimum 100%. |
| `composer test:coverage` | Pest tests with exactly 100% code coverage required. |
| `composer test` | Metadata, formatting, static analysis, type coverage, and tests with coverage. |
| `composer test:all` | Alias for the full `test` script. |
| `composer test:distribution` | Build and inspect the ZIP, install it in an isolated Laravel consumer, and verify discovery, publishing, and cached configuration. |

Code coverage requires a supported coverage driver such as PCOV or Xdebug with coverage enabled. CI uses PHP 8.5 and PCOV. Run a focused test while implementing a behavior change, then the full suite before submitting:

```bash
vendor/bin/pest tests/Unit/CanonicalJsonTest.php
composer test
```

CI checks the lowest and latest supported dependency sets on PHP 8.5, audits runtime dependencies, and runs the distribution check after both quality jobs pass. The distribution command also needs `ext-zip`, Git, a POSIX shell, and network access for Composer. It installs its consumer dependencies under an ignored temporary directory and removes that directory on exit; it does not change the package's installed dependencies.

Dependency checks use the bounds in `composer.json`; the library's local `composer.lock` is ignored. Run minimum-version experiments in a separate checkout so normal development dependencies remain available. See [releasing](releasing.md) for the release procedure.

## Change contracts deliberately

Preserve exact JSON object/list identity, duplicate-key rejection, deterministic encoding, and numeric safety. A change to canonical bytes can change persisted fingerprints, even if ordinary decoded values appear equivalent. Cover representative byte output and hash equality with explicit expectations when changing encoding.

For a new or changed limit, keep the option constructor, Laravel configuration, provider binding, documentation, and behavioral tests consistent. Exercise a value at the limit and one just beyond it. Test container resolution when behavior depends on published configuration; constructing the service directly cannot verify that path.

For schema changes, test both definition acceptance and runtime rejection. Keep bounded schemas and the broader subset distinct. Include nullability versus field presence, undeclared properties, and object/list identity where relevant. Avoid weakening the schema policy merely to make a fixture pass.

Use `declare(strict_types=1)`, the existing namespaces, and precise PHP types. Keep application concerns such as business-specific schemas, request error messages, and secret policy outside this package. The package has one public namespace and does not need compatibility aliases.

## Documentation and agent resources

Keep executable examples aligned with the public classes and configured defaults. Verify relative links, PHP snippet syntax, and example behavior after edits. Check the YAML frontmatter of each `SKILL.md` and render the guideline through Boost, especially after adding Blade-sensitive text or PHP examples.

Test Boost discovery and skill writing in a disposable consumer. Confirm both skills are selected, written under the correct names, and still locate the package docs after being copied. The [agentic development guide](agentic-development.md) describes installation and expected resources.

Documentation-only edits do not require new behavior tests. Existing tests remain the source of evidence for package behavior; add focused tests when changing that behavior.
