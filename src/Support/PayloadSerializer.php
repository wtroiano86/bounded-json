<?php

declare(strict_types=1);

namespace Wtroiano\BoundedJson\Support;

use stdClass;

final readonly class PayloadSerializer
{
    public function __construct(private CanonicalJson $json) {}

    public function decode(?string $json): mixed
    {
        return $json === null ? null : $this->redact($this->json->decode($json));
    }

    public function redact(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $result = new stdClass;
            foreach (get_object_vars($value) as $key => $child) {
                $normalized = mb_strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', (string) $key) ?? (string) $key);
                $result->{$key} = preg_match('/(?:^|[_-])(?:passwords?|secrets?|tokens?|authorization|cookies?|credentials?|api[_-]?key|private[_-]?key)(?:$|[_-])/', $normalized) === 1 ? '[redacted]' : $this->redact($child);
            }

            return $result;
        }

        if (is_array($value)) {
            return array_map($this->redact(...), $value);
        }

        if (is_string($value)) {
            return preg_replace(['/\bBearer\s+[^\s]+/i', '/\bsk-[a-zA-Z0-9_-]{16,}/'], '[redacted]', $value);
        }

        return $value;
    }
}
