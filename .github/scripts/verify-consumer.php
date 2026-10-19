<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Wtroiano\BoundedJson\Exceptions\JsonValueInvalid;
use Wtroiano\BoundedJson\Support\BoundedSchemaValidator;
use Wtroiano\BoundedJson\Support\CanonicalJson;

require $argv[1].'/vendor/autoload.php';

$app = require $argv[1].'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$json = $app->make(CanonicalJson::class);
$validator = $app->make(BoundedSchemaValidator::class);

if (($argv[2] ?? null) === 'cached') {
    if (! $app->configurationIsCached() || $json->encode(str_repeat('a', 126)) !== '"'.str_repeat('a', 126).'"') {
        throw new RuntimeException('The installed package failed with cached configuration.');
    }

    try {
        $json->encode(str_repeat('a', 127));
        throw new RuntimeException('The installed package ignored the cached JSON byte limit.');
    } catch (InvalidArgumentException) {
        // The configured 128-byte limit rejects this 129-byte JSON string.
    }

    try {
        $validator->validateDefinition($json->decodeObject('{"type":"string","maxLength":6}'));
        throw new RuntimeException('The installed package ignored the cached schema string ceiling.');
    } catch (InvalidArgumentException) {
        // The configured schema ceiling is five characters.
    }

    $validator->validateValue($json->decodeObject('{"type":"string","maxLength":5}'), 'hello');
    echo 'Cached configuration, container options, and bounded validation passed.'.PHP_EOL;

    exit(0);
}

$value = $json->decodeObject('{"b":2,"a":1}');
if ($json->encode($value) !== '{"a":1,"b":2}' || $json->hash($value) !== hash('sha256', '{"a":1,"b":2}')) {
    throw new RuntimeException('The installed package failed canonical encoding or hashing.');
}

$schema = $json->decodeObject('{"type":"string","maxLength":5}');
$validator->validateValue($schema, 'hello');
try {
    $validator->validateValue($schema, 'longer');
    throw new RuntimeException('The installed package accepted a value outside its schema bounds.');
} catch (JsonValueInvalid) {
    // Rejection also verifies exception autoloading from the release archive.
}

if ($kernel->call('vendor:publish', ['--tag' => 'bounded-json-config', '--no-interaction' => true]) !== 0) {
    throw new RuntimeException('The installed configuration could not be published.');
}

$configPath = $app->configPath('bounded-json.php');
if (! is_file($configPath)) {
    throw new RuntimeException('Auto-discovery did not register the publishable configuration.');
}

$configuration = require $configPath;
$configuration['max_bytes'] = 128;
$configuration['max_string_length'] = 5;
file_put_contents($configPath, '<?php'.PHP_EOL.'return '.var_export($configuration, true).';'.PHP_EOL);
if ($kernel->call('config:cache', ['--no-interaction' => true]) !== 0) {
    throw new RuntimeException('The consumer configuration could not be cached.');
}

echo 'Archive installation, Laravel discovery, canonicalization, validation, and configuration publishing passed.'.PHP_EOL;
