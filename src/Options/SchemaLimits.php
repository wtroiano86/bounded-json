<?php

declare(strict_types=1);

namespace Wtroiano\BoundedJson\Options;

use InvalidArgumentException;

final readonly class SchemaLimits
{
    public function __construct(
        public int $maxDepth = 16,
        public int $maxEvaluationDepth = 32,
        public int $maxArrayItems = 10000,
        public int $maxStringLength = 10000,
    ) {
        throw_unless($maxDepth >= 0, InvalidArgumentException::class, 'Schema maxDepth must be nonnegative.');
        throw_unless($maxEvaluationDepth >= 0, InvalidArgumentException::class, 'Schema maxEvaluationDepth must be nonnegative.');
        throw_unless($maxArrayItems >= 0, InvalidArgumentException::class, 'Schema maxArrayItems must be nonnegative.');
        throw_unless($maxStringLength >= 0, InvalidArgumentException::class, 'Schema maxStringLength must be nonnegative.');
    }
}
