# Laravel Boost and agentic development

[Documentation index](../README.md#documentation)

This package ships a short Boost guideline plus two focused skills. The guideline provides essential package conventions; the skills supply workflows when an agent is implementing an integration or working on schemas.

| Resource | Purpose |
| --- | --- |
| [Core guideline](../resources/boost/guidelines/core.blade.php) | Service resolution, JSON identity, validator selection, limits, and error boundaries. |
| [bounded-json-integration](../resources/boost/skills/bounded-json-integration/SKILL.md) | Configure the package, decode/canonicalize/hash documents, use typed access, and redact display copies. |
| [bounded-json-schema-validation](../resources/boost/skills/bounded-json-schema-validation/SKILL.md) | Author bounded schemas, select the broader subset when needed, and verify accepted/rejected values. |

The resources use Boost's documented package paths and the Agent Skills frontmatter format. See Laravel's [package guideline documentation](https://laravel.com/docs/13.x/boost#third-party-package-ai-guidelines) and [package skill documentation](https://laravel.com/docs/13.x/boost#third-party-package-skills).

## Install in a consuming Laravel application

Install `wtroiano/bounded-json` as a direct Composer dependency of the application, using the [package installation instructions](../README.md#requirements-and-installation). Then install Boost as development tooling if the application does not already use it:

```bash
composer require laravel/boost --dev
php artisan boost:install
```

Select the application's coding agents, enable guidelines and skills, and include `wtroiano/bounded-json` when Boost offers third-party package resources. Follow the agent setup shown by the installer. These are the commands documented by [Laravel Boost](https://laravel.com/docs/13.x/boost#installation); Boost is not required to run this package.

For an application with Boost already configured, discover newly installed package resources interactively:

```bash
php artisan boost:update
```

Select this package if prompted. If guidelines or skills were disabled in the existing setup, rerun `boost:install` to enable them. Run `boost:update` again after package upgrades to refresh the generated guidance and skill copies.

The integration is checked against Boost 2.10.0. In that version, third-party discovery considers direct requirements, so a package pulled in only by another dependency will not appear automatically. Noninteractive updates and Composer-script updates refresh existing selections without prompting to select a new package. `--no-discover` also skips new-package selection. These details follow the [Boost discovery implementation](https://github.com/laravel/boost/blob/v2.10.0/src/Install/ThirdPartyPackage.php) and [update command](https://github.com/laravel/boost/blob/v2.10.0/src/Console/UpdateCommand.php).

## Verify installation

1. Run `composer show wtroiano/bounded-json --path` to locate the installed package. Confirm it contains `resources/boost/guidelines/core.blade.php` and both skill directories.
2. Check the application's generated agent guidelines for the `Bounded JSON` section.
3. Check the selected agent's skills directory for `bounded-json-integration/SKILL.md` and `bounded-json-schema-validation/SKILL.md`. The destination depends on the selected agent; Boost copies the package resources there.
4. Start or refresh the coding session according to the agent's normal instructions, then ask it to use the relevant skill for a concrete task.

If resources are absent, check the installed package version, direct dependency status, Boost selections, and whether skills were enabled. Consult the installed Boost version's documentation if its commands or discovery behavior differ.

Skills locate the package through Composer before reading documentation. Their documentation paths are relative to the **package root**, not the copied skill folder. This keeps them usable with custom Composer vendor directories and symlinked path repositories.

## Example tasks

Prompts can name the relevant skill explicitly:

```text
Use bounded-json-integration to fingerprint a JSON document accepted by our API.
Use our configured limits, preserve object/list identity, and test duplicate keys
and oversized input at the request boundary.
```

```text
Use bounded-json-schema-validation to define an object with a required title,
an optional nullable note, and at most five tags. Reject undeclared properties
and test the declared string and array boundaries.
```

```text
Use bounded-json-integration to apply a smaller JSON byte limit to one import
operation without changing the application's default policy.
```

For tools without Boost support, point the agent at the package's `README.md` and the relevant `SKILL.md`, or install the skill folders through that tool's supported mechanism. Standalone PHP consumers can use the same skills, following their explicit-options path instead of Laravel container resolution.

## Maintaining the resources

Keep always-loaded guidance short. Put task-specific steps in the skills and detailed behavior in the documentation. Application-specific naming, accepted schemas, logging policy, and HTTP responses belong to the consuming application.

The resource layout is:

```text
resources/boost/
├── guidelines/
│   └── core.blade.php
└── skills/
    ├── bounded-json-integration/
    │   └── SKILL.md
    └── bounded-json-schema-validation/
        └── SKILL.md
```

Each skill has a folder-matching `name` and an actionable `description` in YAML frontmatter. The guideline is a Blade template containing literal Markdown. Keep PHP examples literal during rendering and avoid environment-dependent instructions.

Update the docs, guideline, and relevant skill together when changing a public API, configuration key, accepted schema keyword, or exception contract. Verify their discovery and rendered output in a disposable Laravel application with the package installed as a direct dependency. Do not use a real application's generated agent files as a test fixture. See [package development](development.md) for source and test commands.
