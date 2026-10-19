<?php

declare(strict_types=1);

namespace Wtroiano\BoundedJson\Providers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Wtroiano\BoundedJson\Options\JsonLimits;
use Wtroiano\BoundedJson\Options\SchemaLimits;

final class BoundedJsonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/bounded-json.php', 'bounded-json');

        $this->app->bind(JsonLimits::class, fn (): JsonLimits => new JsonLimits(
            maxBytes: $this->integer('max_bytes'),
            maxDepth: $this->integer('max_json_depth'),
        ));
        $this->app->bind(SchemaLimits::class, fn (): SchemaLimits => new SchemaLimits(
            maxDepth: $this->integer('max_schema_depth'),
            maxEvaluationDepth: $this->integer('max_evaluation_depth'),
            maxArrayItems: $this->integer('max_array_items'),
            maxStringLength: $this->integer('max_string_length'),
        ));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/bounded-json.php' => $this->app->configPath('bounded-json.php'),
        ], 'bounded-json-config');
    }

    private function integer(string $key): int
    {
        $value = $this->app->make(Repository::class)->get('bounded-json.'.$key);
        throw_unless(is_int($value), InvalidArgumentException::class, 'Configuration bounded-json.'.$key.' must be an integer.');

        return $value;
    }
}
