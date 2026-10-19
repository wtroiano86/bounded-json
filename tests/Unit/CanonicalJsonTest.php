<?php

declare(strict_types=1);

use Wtroiano\BoundedJson\Options\JsonLimits;
use Wtroiano\BoundedJson\Support\CanonicalJson;

test('canonicalizes the safe-range RFC 8785 example values without rounding numbers', function (): void {
    $json = <<<'JSON'
{"numbers":[333333333.33333329,1E3,4.50,2e-3,0.000000000000000000000000001],"string":"\u20ac$\u000F\u000aA'\u0042\u0022\u005c\\\"\/","literals":[null,true,false]}
JSON;

    expect((new CanonicalJson)->encode((new CanonicalJson)->decode($json)))
        ->toBe('{"literals":[null,true,false],"numbers":[333333333.3333333,1000,4.5,0.002,1e-27],"string":"€$\\u000f\\nA\'B\\"\\\\\\\\\\"/"}');
});

test('hashes reordered objects alike while retaining array order and empty-object identity', function (): void {
    $canonical = new CanonicalJson;

    expect($canonical->hash($canonical->decode('{"b":2,"a":1}')))
        ->toBe($canonical->hash($canonical->decode('{"a":1.0,"b":2}')))
        ->not->toBe($canonical->hash($canonical->decode('{"a":1,"b":2,"c":[]}')));

    expect($canonical->encode($canonical->decode('{"empty":{}}')))->toBe('{"empty":{}}');
    expect($canonical->encode($canonical->decode('{"empty":[]}')))->toBe('{"empty":[]}');
});

test('rejects duplicate keys including equivalent escaped spellings', function (): void {
    $canonical = new CanonicalJson;

    expect(fn (): mixed => $canonical->decode('{"a":1,"a":2}'))->toThrow(InvalidArgumentException::class);
    expect(fn (): mixed => $canonical->decode('{"a":1,"\\u0061":2}'))->toThrow(InvalidArgumentException::class);
});

test('rejects unsafe integers and nonfinite numbers', function (): void {
    $canonical = new CanonicalJson;

    expect(fn (): mixed => $canonical->decode('{"id":9007199254740992}'))->toThrow(InvalidArgumentException::class);
    expect(fn (): mixed => $canonical->decode('{"id":9223372036854775808}'))->toThrow(InvalidArgumentException::class);
    expect(fn (): string => $canonical->encode(INF))->toThrow(InvalidArgumentException::class);
});

test('checks duplicate keys after long strings without exhausting the regex stack', function (string $unit): void {
    $canonical = new CanonicalJson;
    $string = '"'.str_repeat($unit, 30000).'"';

    expect($canonical->decodeObject('{"pad":'.$string.',"a":1}')->a)->toBe(1);
    expect(fn () => $canonical->decode('{"pad":'.$string.',"a":1,"\\u0061":2}'))
        ->toThrow(InvalidArgumentException::class, 'Duplicate JSON object key: a');
})->with(['plain' => 'a', 'escaped' => '\\n', 'unicode escape' => '\\u0061', 'multibyte' => '😀']);

test('rejects JSON when token validation cannot complete', function (): void {
    $previous = ini_get('pcre.backtrack_limit');
    ini_set('pcre.backtrack_limit', '0');

    try {
        expect(fn () => (new CanonicalJson)->decode('{"a":1,"a":2}'))
            ->toThrow(InvalidArgumentException::class, 'JSON token validation failed:');
    } finally {
        ini_set('pcre.backtrack_limit', $previous);
    }
});

test('bounds payload bytes independently of Unicode character count', function (): void {
    $canonical = new CanonicalJson;
    $value = str_repeat('😀', 65536);

    expect(fn (): string => $canonical->encode($value))->toThrow(InvalidArgumentException::class);
    expect(fn (): mixed => $canonical->decode('"'.$value.'"'))->toThrow(InvalidArgumentException::class);
});

test('uses UTF-16 key order and retains Unicode line separators without normalization', function (): void {
    $canonical = new CanonicalJson;
    $value = $canonical->decode('{"€":"Euro","\\r":"Return","דּ":"Hebrew","1":"One","😀":"Emoji","\\u0080":"Control","ö":"Latin"}');

    expect($canonical->encode($value))->toBe('{"\\r":"Return","1":"One","'."\u{0080}".'":"Control","ö":"Latin","€":"Euro","😀":"Emoji","דּ":"Hebrew"}');
    expect($canonical->encode("\u{2028}\u{2029}"))->toBe('"'."\u{2028}\u{2029}".'"');
});

test('normalizes decimal and exponent boundaries independently of PHP precision settings', function (): void {
    $canonical = new CanonicalJson;
    $previous = ini_get('serialize_precision');
    ini_set('serialize_precision', '5');
    try {
        expect($canonical->encode([1e-7, 1e-6, 1e14, 1e15, -0.0, 0.12345678912345678]))
            ->toBe('[1e-7,0.000001,100000000000000,1000000000000000,0,0.12345678912345678]');
        expect(ini_get('serialize_precision'))->toBe('5');
    } finally {
        ini_set('serialize_precision', $previous);
    }
});

test('rejects unsafe integers regardless of their decimal or exponent spelling', function (string $number): void {
    $canonical = new CanonicalJson;
    expect(fn (): mixed => $canonical->decode($number))->toThrow(InvalidArgumentException::class);
    expect(fn (): string => $canonical->hash((float) $number))->toThrow(InvalidArgumentException::class);
})->with(['9007199254740992', '9007199254740992.0', '9007199254740993.0', '9.007199254740993e15', '-9007199254740993.0', '1e30']);

test('rejects malformed JSON invalid Unicode and non JSON objects', function (): void {
    $canonical = new CanonicalJson;
    expect(fn (): mixed => $canonical->decode('{'))->toThrow(InvalidArgumentException::class, 'Invalid JSON:');
    expect(fn (): string => $canonical->encode("\xB1"))->toThrow(InvalidArgumentException::class, 'Invalid Unicode');
    expect(fn (): string => $canonical->encode(new DateTimeImmutable))->toThrow(InvalidArgumentException::class, 'Only JSON values');
    expect(fn (): stdClass => $canonical->decodeObject('[]'))->toThrow(InvalidArgumentException::class, 'Expected a JSON object');
});

test('enforces custom byte limits on input and canonical output', function (): void {
    $json = new CanonicalJson(new JsonLimits(maxBytes: 6));

    expect($json->encode('😀'))->toBe('"😀"')
        ->and($json->decode('"😀"'))->toBe('😀');
    expect(fn () => $json->encode('😀x'))->toThrow(InvalidArgumentException::class, '6 byte');
    expect(fn () => $json->decode(' "😀"'))->toThrow(InvalidArgumentException::class, '6 byte');
    expect(fn () => $json->hash('😀x'))->toThrow(InvalidArgumentException::class, '6 byte');
    expect(fn () => $json->decode('1e-6'))->toThrow(InvalidArgumentException::class, '6 byte');
});

test('allows larger payloads through explicitly configured instances', function (): void {
    $json = new CanonicalJson(new JsonLimits(maxBytes: 300000));
    $value = str_repeat('a', 262144);

    expect($json->decode($json->encode($value)))->toBe($value);
    expect(fn () => (new CanonicalJson)->encode($value))->toThrow(InvalidArgumentException::class);
});

test('uses the same value depth boundary when encoding and decoding', function (int $depth, string $valid, string $invalid): void {
    $json = new CanonicalJson(new JsonLimits(maxDepth: $depth));

    expect($json->encode($json->decode($valid)))->toBe($valid);
    expect(fn () => $json->decode($invalid))->toThrow(InvalidArgumentException::class);
    expect(fn () => $json->encode(json_decode($invalid, flags: JSON_THROW_ON_ERROR)))
        ->toThrow(InvalidArgumentException::class, $depth.' levels');
})->with([
    'root scalar' => [0, '1', '[1]'],
    'empty root array' => [0, '[]', '[[]]'],
    'empty root object' => [0, '{}', '{"a":{}}'],
    'nested scalar' => [1, '[1]', '[[1]]'],
    'nested empty array' => [1, '[[]]', '[[[]]]'],
    'nested empty object' => [1, '{"a":{}}', '{"a":{"b":{}}}'],
]);

test('allows JSON deeper than the former fixed limit', function (): void {
    $json = new CanonicalJson(new JsonLimits(maxDepth: 65));
    $document = str_repeat('[', 65).'0'.str_repeat(']', 65);

    expect($json->encode($json->decode($document)))->toBe($document);
    expect(fn () => (new CanonicalJson)->decode($document))->toThrow(InvalidArgumentException::class);
});
