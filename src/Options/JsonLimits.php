<?php

declare(strict_types=1);

namespace Wtroiano\BoundedJson\Options;

use InvalidArgumentException;

final readonly class JsonLimits
{
    /** @var int<0, 2147483645> */
    public int $maxDepth;

    public function __construct(
        public int $maxBytes = 262144,
        int $maxDepth = 64,
    ) {
        throw_unless($maxBytes > 0, InvalidArgumentException::class, 'JSON maxBytes must be positive.');
        throw_unless($maxDepth >= 0 && $maxDepth <= 2147483645, InvalidArgumentException::class, 'JSON maxDepth must be between 0 and 2147483645.');

        $this->maxDepth = $maxDepth;
    }
}
