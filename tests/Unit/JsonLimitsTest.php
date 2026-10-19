<?php

declare(strict_types=1);

use Wtroiano\BoundedJson\Options\JsonLimits;

it('rejects invalid JSON limits at construction', function (array $options, string $name): void {
    expect(fn () => new JsonLimits(...$options))->toThrow(InvalidArgumentException::class, $name);
})->with([
    'zero bytes' => [['maxBytes' => 0], 'maxBytes'],
    'negative bytes' => [['maxBytes' => -1], 'maxBytes'],
    'negative depth' => [['maxDepth' => -1], 'maxDepth'],
    'decoder depth overflow' => [['maxDepth' => 2147483646], 'maxDepth'],
]);
