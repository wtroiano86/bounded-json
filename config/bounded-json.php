<?php

declare(strict_types=1);

return [
    // Applies to both the input bytes and the canonical output bytes.
    'max_bytes' => 262144,

    // JSON values and bounded schema definitions count the root at depth zero.
    'max_json_depth' => 64,
    'max_schema_depth' => 16,

    // Evaluation also counts schema references and alternative branches.
    'max_evaluation_depth' => 32,

    // Ceilings for schema-declared bounds; string length counts Unicode characters.
    'max_array_items' => 10000,
    'max_string_length' => 10000,
];
