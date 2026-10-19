<?php

declare(strict_types=1);

$tag = $argv[1] ?? '';
if (preg_match('/\Av(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\z/', $tag) !== 1) {
    throw new RuntimeException('Use a stable release tag such as v0.1.0.');
}

$version = substr($tag, 1);
$changelog = file_get_contents(__DIR__.'/../../CHANGELOG.md');
if (preg_match('/^## '.preg_quote($version, '/').' — ([0-9]{4}-[0-9]{2}-[0-9]{2})$/m', $changelog, $matches) !== 1) {
    throw new RuntimeException('Add a dated changelog heading for '.$version.' before tagging: ## '.$version.' — YYYY-MM-DD');
}

$date = DateTimeImmutable::createFromFormat('!Y-m-d', $matches[1]);
if ($date === false || $date->format('Y-m-d') !== $matches[1]) {
    throw new RuntimeException('The changelog release date is invalid.');
}

$package = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
if ($package['name'] !== 'wtroiano/bounded-json' || isset($package['version'])) {
    throw new RuntimeException('Release wtroiano/bounded-json using the Git tag as its Composer version.');
}

echo 'Release metadata matches '.$tag.'.'.PHP_EOL;
