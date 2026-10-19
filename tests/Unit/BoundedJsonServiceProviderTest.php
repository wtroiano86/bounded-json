<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Wtroiano\BoundedJson\Options\JsonLimits;
use Wtroiano\BoundedJson\Options\SchemaLimits;
use Wtroiano\BoundedJson\Providers\BoundedJsonServiceProvider;
use Wtroiano\BoundedJson\Support\CanonicalJson;

it('merges host configuration into limits and exposes the publishable configuration', function (): void {
    $values = ['bounded-json' => ['max_bytes' => 6, 'max_schema_depth' => 2, 'max_array_items' => 3, 'max_string_length' => 5]];
    $repository = $this->createStub(Repository::class);
    $repository->method('get')->willReturnCallback(function (string $key, mixed $default = null) use (&$values): mixed {
        return Arr::get($values, $key, $default);
    });
    $repository->method('set')->willReturnCallback(function (string $key, mixed $value) use (&$values): void {
        Arr::set($values, $key, $value);
    });
    $bindings = [];
    $app = $this->createStub(Application::class);
    $app->method('make')->willReturn($repository);
    $app->method('bind')->willReturnCallback(function (string $type, Closure $factory) use (&$bindings): void {
        $bindings[$type] = $factory;
    });
    $app->method('configPath')->willReturn('/app/config/bounded-json.php');
    $provider = new BoundedJsonServiceProvider($app);

    $provider->register();
    $provider->boot();
    $json = new CanonicalJson($bindings[JsonLimits::class]());

    expect($json->decode('"😀"'))->toBe('😀');
    expect(fn () => $json->decode('"😀x"'))->toThrow(InvalidArgumentException::class, '6 byte');
    expect($bindings[SchemaLimits::class]())->toEqual(new SchemaLimits(maxDepth: 2, maxArrayItems: 3, maxStringLength: 5));
    $published = BoundedJsonServiceProvider::pathsToPublish(BoundedJsonServiceProvider::class, 'bounded-json-config');
    expect(array_values($published))->toBe(['/app/config/bounded-json.php']);
    expect(require array_key_first($published))->toMatchArray(['max_bytes' => 262144, 'max_json_depth' => 64]);
});

it('rejects noninteger Laravel configuration without coercing it', function (): void {
    $repository = $this->createStub(Repository::class);
    $repository->method('get')->willReturnCallback(fn (string $key, mixed $default = null): mixed => $key === 'bounded-json' ? [] : '1024');
    $bindings = [];
    $app = $this->createStub(Application::class);
    $app->method('make')->willReturn($repository);
    $app->method('bind')->willReturnCallback(function (string $type, Closure $factory) use (&$bindings): void {
        $bindings[$type] = $factory;
    });
    (new BoundedJsonServiceProvider($app))->register();

    expect(fn () => $bindings[JsonLimits::class]())->toThrow(InvalidArgumentException::class, 'bounded-json.max_bytes');
    expect(fn () => $bindings[SchemaLimits::class]())->toThrow(InvalidArgumentException::class, 'bounded-json.max_schema_depth');
});
