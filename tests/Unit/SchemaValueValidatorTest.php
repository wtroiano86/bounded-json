<?php

declare(strict_types=1);

use Wtroiano\BoundedJson\Support\SchemaValueValidator;

it('validates supported schema assertions and rejects values outside them', function (array $schema, string $valid, string $invalid): void {
    $validator = new SchemaValueValidator;
    $validator->validate($schema, json_decode($valid, flags: JSON_THROW_ON_ERROR));

    expect(fn () => $validator->validate($schema, json_decode($invalid, flags: JSON_THROW_ON_ERROR)))->toThrow(RuntimeException::class);
})->with([
    'local definitions' => [['type' => 'object', '$defs' => ['id' => ['type' => 'integer']], 'properties' => ['id' => ['$ref' => '#/$defs/id']]], '{"id":1}', '{"id":"one"}'],
    'legacy definitions' => [['definitions' => ['id' => ['const' => 2]], '$ref' => '#/definitions/id'], '2', '3'],
    'all alternatives' => [['allOf' => [['type' => 'number'], ['minimum' => 2]]], '2', '1'],
    'any alternative' => [['anyOf' => [['type' => 'string'], ['type' => 'null']]], 'null', '1'],
    'one alternative' => [['oneOf' => [['type' => 'integer'], ['type' => 'string']]], '"a"', 'null'],
    'negation' => [['not' => ['type' => 'string']], '1', '"a"'],
    'enum values' => [['enum' => [1, 2]], '1.0', '3'],
    'object enum values' => [['enum' => [['id' => 1]]], '{"id":1}', '{"id":2,"other":3}'],
    'object equality' => [['const' => ['id' => 1]], '{"id":1}', '{"id":2}'],
    'string bounds' => [['type' => 'string', 'minLength' => 1, 'maxLength' => 3, 'pattern' => '^[a-z]+$'], '"abc"', '"ABC"'],
    'array bounds' => [['type' => 'array', 'minItems' => 1, 'maxItems' => 2, 'items' => true], '[1]', '[]'],
    'object bounds' => [['type' => 'object', 'minProperties' => 1, 'maxProperties' => 1, 'additionalProperties' => true], '{"id":1}', '{}'],
    'exclusive bounds and multiple' => [['type' => 'number', 'exclusiveMinimum' => 0, 'exclusiveMaximum' => 10, 'multipleOf' => 2], '4', '3'],
]);

it('rejects invalid and unsupported schema structures before dispatch', function (array $schema, string $arguments = '{}'): void {
    expect(fn () => (new SchemaValueValidator)->validate($schema, json_decode($arguments, flags: JSON_THROW_ON_ERROR)))->toThrow(RuntimeException::class);
})->with([
    [['$ref' => 'https://elsewhere.invalid/schema']], [['$ref' => '#/missing']],
    [['$ref' => '#/title', 'title' => 'not a schema']], [['$ref' => 2]],
    [['properties' => 'invalid']], [['items' => 'invalid']], [['type' => 'invalid']],
    [['multipleOf' => 'invalid'], '4'], [['multipleOf' => 0], '4'], [['allOf' => [false]]],
]);

it('rejects recursive schemas instead of exhausting the stack', function (): void {
    expect(fn () => (new SchemaValueValidator)->validate(['$defs' => ['node' => ['$ref' => '#/$defs/node']]], new stdClass))->toThrow(RuntimeException::class, 'nesting');
});

it('compares native JSON object constants by their properties', function (): void {
    $schema = ['const' => (object) ['id' => 1]];
    (new SchemaValueValidator)->validate($schema, (object) ['id' => 1]);
    expect(fn () => (new SchemaValueValidator)->validate($schema, (object) ['id' => 2]))->toThrow(RuntimeException::class);
});
