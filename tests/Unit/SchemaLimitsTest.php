<?php

declare(strict_types=1);

use Wtroiano\BoundedJson\Options\SchemaLimits;

it('rejects negative schema limits at construction', function (string $name): void {
    expect(fn () => new SchemaLimits(...[$name => -1]))->toThrow(InvalidArgumentException::class, $name);
})->with(['maxDepth', 'maxEvaluationDepth', 'maxArrayItems', 'maxStringLength']);
