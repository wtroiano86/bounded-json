<?php

declare(strict_types=1);

namespace Wtroiano\BoundedJson;

use Illuminate\Contracts\Container\Container;

/** @return array<class-string, class-string> */
function legacyAliases(): array
{
    return [
        'Wtroiano\Helm\Support\Json\CanonicalJson' => 'Wtroiano\BoundedJson\Support\CanonicalJson',
        'Wtroiano\Helm\Support\Json\JsonData' => 'Wtroiano\BoundedJson\Support\JsonData',
        'Wtroiano\Helm\Support\Json\BoundedSchemaValidator' => 'Wtroiano\BoundedJson\Support\BoundedSchemaValidator',
        'Wtroiano\Helm\Support\Json\PayloadSerializer' => 'Wtroiano\BoundedJson\Support\PayloadSerializer',
        'Wtroiano\Helm\Support\Json\SchemaValueValidator' => 'Wtroiano\BoundedJson\Support\SchemaValueValidator',
        'Wtroiano\Helm\Exceptions\JsonValueInvalid' => 'Wtroiano\BoundedJson\Exceptions\JsonValueInvalid',
    ];
}

function registerLegacyAliases(Container $container): void
{
    foreach (legacyAliases() as $legacy => $current) {
        $container->alias($current, $legacy);
    }
}

foreach (legacyAliases() as $legacy => $current) {
    if (! class_exists($legacy, false) && ! interface_exists($legacy, false)) {
        class_alias($current, $legacy);
    }
}
