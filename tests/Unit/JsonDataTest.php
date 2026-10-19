<?php

declare(strict_types=1);

use Wtroiano\BoundedJson\Support\JsonData;

it('preserves JSON scalar types without coercion', function (): void {
    $object = (object) ['0' => 'value'];
    expect(JsonData::object($object))->toBe($object)
        ->and(JsonData::string('0'))->toBe('0')
        ->and(JsonData::integer(0))->toBe(0)
        ->and(JsonData::boolean(false))->toBeFalse();
});

it('retains checked map and list contents', function (): void {
    $object = (object) ['title' => 'Draft'];
    expect(JsonData::map((object) ['draft' => $object]))->toBe(['draft' => $object])
        ->and(JsonData::map(['draft' => null]))->toBe(['draft' => null])
        ->and(JsonData::objects((object) ['draft' => $object]))->toBe(['draft' => $object])
        ->and(JsonData::list([null, 1, false]))->toBe([null, 1, false])
        ->and(JsonData::objectList([$object]))->toBe([$object])
        ->and(JsonData::strings(['', '0']))->toBe(['', '0'])
        ->and(JsonData::map((object) []))->toBe([])
        ->and(JsonData::list([]))->toBe([]);
});

it('rejects invalid workflow value shapes rather than coercing them', function (string $method, mixed $value): void {
    expect(fn () => JsonData::$method($value))->toThrow(InvalidArgumentException::class);
})->with([
    'array is not an object' => ['object', []],
    'null is not an object' => ['object', null],
    'number is not a string' => ['string', 1],
    'numeric string is not an integer' => ['integer', '1'],
    'float is not an integer' => ['integer', 1.0],
    'integer is not boolean' => ['boolean', 1],
    'scalar is not a map' => ['map', 'value'],
    'map keys must be strings' => ['map', [1]],
    'object map values must be objects' => ['objects', ['draft' => 'value']],
    'object is not a list' => ['list', (object) []],
    'associative array is not a list' => ['list', ['draft' => 1]],
    'object list values must be objects' => ['objectList', [1]],
    'string list values must be strings' => ['strings', [false]],
]);
