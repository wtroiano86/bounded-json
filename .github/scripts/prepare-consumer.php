<?php

declare(strict_types=1);

$directory = $argv[1];
$archivePath = $directory.'/bounded-json.zip';
$archive = new ZipArchive;
if ($archive->open($archivePath) !== true) {
    throw new RuntimeException('Unable to open the package archive.');
}

$requiredFiles = [
    'composer.json', 'LICENSE', 'README.md', 'CHANGELOG.md',
    'config/bounded-json.php',
    'src/Support/CanonicalJson.php', 'src/Support/BoundedSchemaValidator.php',
    'src/Support/SchemaValueValidator.php', 'src/Support/JsonData.php', 'src/Support/PayloadSerializer.php',
    'src/Options/JsonLimits.php', 'src/Options/SchemaLimits.php',
    'src/Exceptions/JsonValueInvalid.php', 'src/Providers/BoundedJsonServiceProvider.php',
    'docs/configuration.md', 'docs/canonical-json.md', 'docs/schema-validation.md',
    'docs/utilities-and-errors.md', 'docs/development.md', 'docs/agentic-development.md', 'docs/releasing.md',
    'resources/boost/guidelines/core.blade.php',
    'resources/boost/skills/bounded-json-integration/SKILL.md',
    'resources/boost/skills/bounded-json-schema-validation/SKILL.md',
];
foreach ($requiredFiles as $required) {
    if ($archive->locateName($required) === false) {
        throw new RuntimeException('The package archive is missing '.$required);
    }
}

for ($index = 0; $index < $archive->numFiles; $index++) {
    $entry = $archive->getNameIndex($index);
    $root = explode('/', $entry)[0];
    if (in_array($root, ['.git', '.github', '.gitattributes', '.gitignore', '.phpstan-cache', '.phpunit.cache', '.phpunit.result.cache', '.php-cs-fixer.cache', '.rector-cache', 'vendor', 'node_modules', 'tests', 'tmp', 'dist', 'workbench', 'composer.lock', 'composer.local.json', 'composer.local.lock', 'auth.json', 'pint.json', 'phpstan.neon', 'phpunit.xml', 'compatibility.php', 'compatibility.json'], true) || $root === '.env' || str_starts_with($root, '.env.')) {
        throw new RuntimeException('The package archive includes an unexpected file: '.$entry);
    }
}

$package = json_decode($archive->getFromName('composer.json'), true, flags: JSON_THROW_ON_ERROR);
$archive->close();
if (isset($package['version'])) {
    throw new RuntimeException('Published package versions must come from Git tags.');
}

// Supply a fixture version because this test installs an archive before a tag exists.
$package['version'] = '0.1.0';
$package['dist'] = ['type' => 'zip', 'url' => $archivePath];
$consumer = [
    'name' => 'bounded-json/distribution-test',
    'type' => 'project',
    'require' => ['laravel/framework' => '^13.34', 'wtroiano/bounded-json' => '0.1.0'],
    'repositories' => [['type' => 'package', 'package' => $package]],
    'config' => ['allow-plugins' => false],
];

mkdir($directory.'/consumer/bootstrap/cache', 0755, true);
mkdir($directory.'/consumer/config', 0755, true);
mkdir($directory.'/consumer/storage/logs', 0755, true);
file_put_contents($directory.'/consumer/composer.json', json_encode($consumer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
file_put_contents($directory.'/consumer/bootstrap/app.php', <<<'PHP'
<?php

declare(strict_types=1);

return Illuminate\Foundation\Application::configure(basePath: dirname(__DIR__))->create();
PHP.PHP_EOL);

echo 'Archive contents passed; prepared an isolated Laravel consumer.'.PHP_EOL;
