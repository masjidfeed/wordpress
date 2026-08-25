<?php

declare(strict_types=1);

$command = 'php -d memory_limit=-1 vendor/bin/php-scoper add-prefix --config=scoper.inc.php --output-dir=vendor-prefixed vendor --force';
passthru($command, $exit_code);
if (0 !== $exit_code) {
    exit($exit_code);
}

$autoload_file = __DIR__ . '/../vendor-prefixed/composer/autoload_real.php';
$contents = file_get_contents($autoload_file);
$loader_class_needle = <<<'PHP'
'Composer\Autoload\ClassLoader' === $class
PHP;
$loader_class_replacement = <<<'PHP'
'Masjid_App\Dependencies\Composer\Autoload\ClassLoader' === $class
PHP;
$contents = str_replace($loader_class_needle, $loader_class_replacement, $contents, $loader_class_replacement_count);
if (1 !== $loader_class_replacement_count) {
    fwrite(STDERR, "Unable to update the scoped Composer class loader name.\n");
    exit(1);
}

$needle = '        $loader->register(\true);';
$replacement = '        $loader->setClassMapAuthoritative(\true);' . PHP_EOL . $needle;
$contents = str_replace($needle, $replacement, $contents, $replacement_count);
if (1 !== $replacement_count) {
    fwrite(STDERR, "Unable to make the scoped autoloader classmap-authoritative.\n");
    exit(1);
}
file_put_contents($autoload_file, $contents);