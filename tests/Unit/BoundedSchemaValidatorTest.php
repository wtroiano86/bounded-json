<?php

declare(strict_types=1);

use Wtroiano\BoundedJson\Options\JsonLimits;
use Wtroiano\BoundedJson\Options\SchemaLimits;
use Wtroiano\BoundedJson\Support\BoundedSchemaValidator;
use Wtroiano\BoundedJson\Support\CanonicalJson;
use Wtroiano\BoundedJson\Support\SchemaValueValidator;

test('accepts a closed bounded schema and validates object values', function (): void {
    $schema = (new CanonicalJson)->decode('{"type":"object","properties":{"name":{"type":"string","minLength":1,"maxLength":12}},"required":["name"],"additionalProperties":false}');
    $validator = new BoundedSchemaValidator(new CanonicalJson, new SchemaValueValidator);

    $validator->validateValue($schema, (object) ['name' => 'Ada']);

    expect(fn () => $validator->validateValue($schema, (object) ['name' => '']))->toThrow(RuntimeException::class);
    expect(fn () => $validator->validateValue($schema, (object) ['name' => 'Ada', 'extra' => true]))->toThrow(RuntimeException::class);
});

test('rejects unknown keywords and unbounded or open containers', function (): void {
    $canonical = new CanonicalJson;
    $validator = new BoundedSchemaValidator(new CanonicalJson, new SchemaValueValidator);

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
    $validator = new BoundedSchemaValidator(new CanonicalJson, new SchemaValueValidator);

    $validator->validateValue($schema, (object) ['0' => true]);

    expect(fn () => $validator->validateValue($schema, [true]))->toThrow(RuntimeException::class);
    expect(fn () => $validator->validateValue($schema, (object) ['0' => false]))->toThrow(Exception::class);
});

test('validates bounded numeric arrays and nested enum membership', function (): void {
    $json = new CanonicalJson;
    $schema = $json->decodeObject('{"type":"array","minItems":1,"maxItems":3,"items":{"type":"number","minimum":0,"maximum":10,"enum":[1,2]}}');
    $validator = new BoundedSchemaValidator(new CanonicalJson, new SchemaValueValidator);
    $validator->validateValue($schema, [1, 2.0]);

    expect(fn () => $validator->validateValue($schema, [3]))->toThrow(Exception::class);
    expect(fn () => $validator->validateValue($schema, []))->toThrow(Exception::class);
});

test('enforces separate ceilings for array sizes and string lengths', function (string $schema): void {
    $json = new CanonicalJson;
    $limits = new SchemaLimits(maxArrayItems: 2, maxStringLength: 4);
    $validator = new BoundedSchemaValidator($json, new SchemaValueValidator($limits), $limits);

    expect(fn () => $validator->validateDefinition($json->decodeObject($schema)))
        ->toThrow(InvalidArgumentException::class, 'between 0 and');
})->with([
    'maximum array size' => '{"type":"array","items":{"type":"boolean"},"maxItems":3}',
    'minimum array size' => '{"type":"array","items":{"type":"boolean"},"minItems":3,"maxItems":2}',
    'maximum string length' => '{"type":"string","maxLength":5}',
    'minimum string length' => '{"type":"string","minLength":5,"maxLength":4}',
]);

test('accepts values at independently configured array and Unicode string limits', function (): void {
    $json = new CanonicalJson;
    $limits = new SchemaLimits(maxArrayItems: 2, maxStringLength: 4);
    $validator = new BoundedSchemaValidator($json, new SchemaValueValidator($limits), $limits);
    $schema = $json->decodeObject('{"type":"array","items":{"type":"string","maxLength":4},"maxItems":2}');

    $validator->validateValue($schema, ['😀😀😀😀', 'abcd']);

    expect(fn () => $validator->validateValue($schema, ['abcde']))->toThrow(RuntimeException::class);
    expect(fn () => $validator->validateValue($schema, ['a', 'b', 'c']))->toThrow(RuntimeException::class);
});

test('allows larger schema bounds when explicitly configured', function (): void {
    $json = new CanonicalJson;
    $limits = new SchemaLimits(maxArrayItems: 10001, maxStringLength: 10001);
    $validator = new BoundedSchemaValidator($json, new SchemaValueValidator($limits), $limits);

    $validator->validateValue($json->decodeObject('{"type":"string","maxLength":10001}'), str_repeat('a', 10001));
    $validator->validateValue($json->decodeObject('{"type":"array","items":{"type":"boolean"},"maxItems":10001}'), array_fill(0, 10001, true));

    expect(fn () => $validator->validateDefinition($json->decodeObject('{"type":"string","maxLength":10002}')))
        ->toThrow(InvalidArgumentException::class, '10001');
});

test('supports zero size ceilings for empty strings and arrays', function (): void {
    $json = new CanonicalJson;
    $limits = new SchemaLimits(maxArrayItems: 0, maxStringLength: 0);
    $validator = new BoundedSchemaValidator($json, new SchemaValueValidator($limits), $limits);

    $validator->validateValue($json->decodeObject('{"type":"string","maxLength":0}'), '');
    $validator->validateValue($json->decodeObject('{"type":"array","items":{"type":"boolean"},"maxItems":0}'), []);

    expect(fn () => $validator->validateDefinition($json->decodeObject('{"type":"string","maxLength":1}')))
        ->toThrow(InvalidArgumentException::class, 'between 0 and 0');
});

test('honors coordinated JSON schema and evaluation depths above the defaults', function (): void {
    $json = new CanonicalJson(new JsonLimits(maxDepth: 70));
    $limits = new SchemaLimits(maxDepth: 33, maxEvaluationDepth: 33);
    $validator = new BoundedSchemaValidator($json, new SchemaValueValidator($limits), $limits);
    $schema = (object) ['type' => 'boolean'];
    $value = true;
    for ($depth = 0; $depth < 33; $depth++) {
        $schema = (object) ['type' => 'array', 'maxItems' => 1, 'items' => $schema];
        $value = [$value];
    }

    $validator->validateValue($schema, $value);

    expect(fn () => (new BoundedSchemaValidator($json, new SchemaValueValidator))->validateDefinition($schema))
        ->toThrow(InvalidArgumentException::class, '16 nested levels');
});
