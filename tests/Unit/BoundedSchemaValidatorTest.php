<?php

declare(strict_types=1);

use Wtroiano\BoundedJson\Support\BoundedSchemaValidator;
use Wtroiano\BoundedJson\Support\CanonicalJson;

test('accepts a closed bounded schema and validates object values', function (): void {
    $schema = (new CanonicalJson)->decode('{"type":"object","properties":{"name":{"type":"string","minLength":1,"maxLength":12}},"required":["name"],"additionalProperties":false}');
    $validator = new BoundedSchemaValidator(new CanonicalJson, new Wtroiano\BoundedJson\Support\SchemaValueValidator);

    $validator->validateValue($schema, (object) ['name' => 'Ada']);

    expect(fn () => $validator->validateValue($schema, (object) ['name' => '']))->toThrow(RuntimeException::class);
    expect(fn () => $validator->validateValue($schema, (object) ['name' => 'Ada', 'extra' => true]))->toThrow(RuntimeException::class);
});

test('rejects unknown keywords and unbounded or open containers', function (): void {
    $canonical = new CanonicalJson;
    $validator = new BoundedSchemaValidator(new CanonicalJson, new Wtroiano\BoundedJson\Support\SchemaValueValidator);

    foreach ([
        '{"type":"string","maxLength":10,"pattern":".*"}',
        '{"type":"string"}',
        '{"type":"array","items":{"type":"boolean"}}',
        '{"type":"object","properties":{},"additionalProperties":true}',
        '{"type":"object","properties":{},"required":["missing"],"additionalProperties":false}',
        '{"type":"string","maxLength":1,"enum":["too long"]}',
        '{"type":[{},"null"]}',
        '{"type":"string","maxLength":1,"enum":null}',
        '{"type":"number","minimum":null}',
        '{"type":"object","properties":{},"required":null,"additionalProperties":false}',
        '{"type":"boolean","title":null}',
    ] as $json) {
        expect(fn () => $validator->validateDefinition($canonical->decode($json)))->toThrow(Exception::class);
    }
});

test('preserves nested object identity when checking enum values', function (): void {
    $canonical = new CanonicalJson;
    $schema = $canonical->decode('{"type":"object","properties":{"0":{"type":"boolean"}},"additionalProperties":false,"enum":[{"0":true}]}');
    $validator = new BoundedSchemaValidator(new CanonicalJson, new Wtroiano\BoundedJson\Support\SchemaValueValidator);

    $validator->validateValue($schema, (object) ['0' => true]);

    expect(fn () => $validator->validateValue($schema, [true]))->toThrow(RuntimeException::class);
    expect(fn () => $validator->validateValue($schema, (object) ['0' => false]))->toThrow(Exception::class);
});

test('validates bounded numeric arrays and nested enum membership', function (): void {
    $json = new CanonicalJson;
    $schema = $json->decodeObject('{"type":"array","minItems":1,"maxItems":3,"items":{"type":"number","minimum":0,"maximum":10,"enum":[1,2]}}');
    $validator = new BoundedSchemaValidator(new CanonicalJson, new Wtroiano\BoundedJson\Support\SchemaValueValidator);
    $validator->validateValue($schema, [1, 2.0]);

    expect(fn () => $validator->validateValue($schema, [3]))->toThrow(Exception::class);
    expect(fn () => $validator->validateValue($schema, []))->toThrow(Exception::class);
});
