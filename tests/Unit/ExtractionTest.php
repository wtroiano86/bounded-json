<?php

declare(strict_types=1);

use Wtroiano\BoundedJson\Exceptions\JsonValueInvalid;
use Wtroiano\BoundedJson\Options\SchemaLimits;
use Wtroiano\BoundedJson\Support\BoundedSchemaValidator;
use Wtroiano\BoundedJson\Support\CanonicalJson;
use Wtroiano\BoundedJson\Support\PayloadSerializer;
use Wtroiano\BoundedJson\Support\SchemaValueValidator;

it('honors an explicit schema depth without an application container', function (): void {
    $schema = (new CanonicalJson)->decodeObject('{"type":"array","maxItems":1,"items":{"type":"boolean"}}');
    $validator = new BoundedSchemaValidator(new CanonicalJson, new SchemaValueValidator, new SchemaLimits(maxDepth: 0));

    expect(fn () => $validator->validateDefinition($schema))->toThrow(InvalidArgumentException::class);
    (new BoundedSchemaValidator(new CanonicalJson, new SchemaValueValidator, new SchemaLimits(maxDepth: 1)))->validateValue($schema, [true]);
});

it('throws a JSON value exception for values outside the schema', function (): void {
    $validator = new BoundedSchemaValidator(new CanonicalJson, new SchemaValueValidator);

    expect(fn () => $validator->validateValue((object) ['type' => 'boolean'], 'invalid'))
        ->toThrow(JsonValueInvalid::class);
});

it('redacts secrets recursively while preserving ordinary payload values', function (): void {
    $serializer = new PayloadSerializer(new CanonicalJson);
    $payload = '{"apiKey":"secret","nested":[{"access_token":"secret","message":"Bearer abc"},42,true,null],"title":"Status"}';

    expect($serializer->decode(null))->toBeNull()
        ->and(json_encode($serializer->decode($payload)))->toBe('{"apiKey":"[redacted]","nested":[{"access_token":"[redacted]","message":"[redacted]"},42,true,null],"title":"Status"}')
        ->and($serializer->redact('key sk-12345678901234567890'))->toBe('key [redacted]');
});

it('accepts nullable schemas and rejects ambiguous unions', function (): void {
    $validator = new BoundedSchemaValidator(new CanonicalJson, new SchemaValueValidator);
    $validator->validateValue((object) ['type' => ['boolean', 'null']], null);
    expect(fn () => $validator->validateDefinition((object) ['type' => ['boolean', 'string']]))
        ->toThrow(InvalidArgumentException::class, 'one type plus null');
});

it('compares unique array entries structurally', function (): void {
    $validator = new SchemaValueValidator;
    $schema = ['type' => 'array', 'uniqueItems' => true];
    $validator->validate($schema, [(object) ['id' => 1], (object) ['id' => 2]]);
    expect(fn () => $validator->validate($schema, [(object) ['id' => 1], (object) ['id' => 1]]))
        ->toThrow(RuntimeException::class);
});
