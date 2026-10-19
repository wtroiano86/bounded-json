# Releasing Bounded JSON

[Documentation index](../README.md#documentation)

The examples below use the initial release, `0.1.0`. Keep each new [changelog](../CHANGELOG.md) entry marked unreleased until publication. Preparing the package, committing a release, and publishing it are separate steps.

## Version and repository metadata

Composer reads published versions from Git tags such as `v0.1.0`; keep the `version` field out of `composer.json`. This follows [Composer's version guidance](https://getcomposer.org/doc/04-schema.md#version).

The package repository is [wtroiano86/bounded-json](https://github.com/wtroiano86/bounded-json), with `origin` set to `https://github.com/wtroiano86/bounded-json.git`. Composer metadata includes this repository's homepage, source, documentation, and issue links. The Composer package name is `wtroiano/bounded-json`.

Review changes to canonical bytes, exception behavior, accepted schemas, defaults, or PHP/Illuminate requirements as public contract changes. During the `0.x` series, use a new minor version for incompatible changes and document the effect on persisted documents and hashes. Use patch releases for compatible fixes.

## Run the release checks

From the package source checkout:

```bash
composer install
composer test
composer audit --locked --no-dev
composer test:distribution
```

`composer test` validates metadata, formatting, static analysis, and 100% type/code coverage. Coverage needs PCOV or an enabled Xdebug coverage mode. The distribution check requires PHP's ZIP extension, Git, a POSIX shell, and Composer access to dependency repositories.

The CI workflow also resolves the lowest and latest versions allowed by the manifest. Wait for both dependency jobs and the distribution job to pass on the release commit. A local run against one dependency set does not substitute for that matrix. Tag builds also check that the stable `vX.Y.Z` tag has a matching, dated changelog entry and that Composer derives the version from Git.

The distribution check builds a ZIP from the current working tree, verifies its contents, and installs it as a dependency in a temporary Laravel application. It checks package discovery, container resolution, encoding and validation, configuration publishing, and fresh-process resolution with cached application configuration. The temporary application is removed on exit. No full Laravel installation is added to this package's runtime requirements.

## Inspect the distribution

To retain an archive for inspection:

```bash
mkdir -p dist
composer archive --format=zip --file=bounded-json --dir=dist
```

The archive includes `composer.json`, `LICENSE`, `README.md`, `CHANGELOG.md`, `src/`, `config/`, `docs/`, and `resources/boost/`. It excludes tests, CI files, development-tool configuration, dependencies, caches, local credentials, lock files, and temporary/build directories through `.gitattributes`.

Composer archives can contain uncommitted working-tree files. A Git tag contains only committed files. Inspect `git status --short` and commit all intended source, documentation, configuration, and Boost resources before tagging. Use an archive from the release commit when distributing a final artifact.

Source checkouts retain the test suite and development tools. Consumer archives intentionally omit them; run development checks from a source checkout.

## Publish the reviewed commit

1. Confirm the repository remote and final version, and update the changelog's unreleased heading with the release date.
2. Review and commit the intended package changes. Confirm the working tree is clean and that CI passes for that commit.
3. Run `composer test:release -- v0.1.0` after dating the changelog, then create an annotated tag for the reviewed commit: `git tag -a v0.1.0 -m "Release 0.1.0"`.
4. Push the reviewed branch and that tag to the configured package repository. Wait for the tag's CI checks to pass, then create its release entry using the matching changelog section.
5. For public Composer distribution, register the repository with Packagist or update its existing entry and confirm the tag is indexed. For private distribution, use the organization's Composer registry or configure the consumer's VCS repository.
6. In a fresh consumer, install `wtroiano/bounded-json:^0.1.0` from the intended registry and verify the installed version and Laravel configuration publishing. Applications using Boost should refresh the selected package resources with `php artisan boost:update`.

Do not move a published tag to different code. Correct a published problem with a new release and a matching changelog entry. The package CI validates changes; it does not publish tags, create hosted releases, or submit packages to registries automatically.

For the initial release, after dating the changelog, reviewing and committing the changes, and running the checks:

```bash
git status --short
git push -u origin master
gh run list --repo wtroiano86/bounded-json --branch master
# Wait for the release commit's Package CI run to pass.
composer test:release -- v0.1.0
git tag -a v0.1.0 -m "Release 0.1.0"
git push origin v0.1.0
gh run list --repo wtroiano86/bounded-json --branch v0.1.0
# Wait for the tag's Package CI run to pass.
mkdir -p dist
git archive --format=zip --output=dist/bounded-json-0.1.0.zip v0.1.0
(cd dist && sha256sum bounded-json-0.1.0.zip > bounded-json-0.1.0.zip.sha256)
```

Copy the `0.1.0` changelog section into `dist/release-notes.md`, then publish the GitHub release and its archive:

```bash
gh release create v0.1.0 \
    dist/bounded-json-0.1.0.zip dist/bounded-json-0.1.0.zip.sha256 \
    --repo wtroiano86/bounded-json \
    --verify-tag --title "v0.1.0" --notes-file dist/release-notes.md
```

GitHub publication does not register the package with Packagist. Until it is indexed there, consumers can install the published tag directly from GitHub:

```bash
composer config repositories.bounded-json vcs https://github.com/wtroiano86/bounded-json
composer require 'wtroiano/bounded-json:^0.1.0'
```
