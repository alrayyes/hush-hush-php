<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withSkip([__DIR__ . '/src/Generated'])
    // No withPhpVersion(): Rector reads composer.json's require.php (>=8.2)
    // on its own, so a bump there is the only edit a future PHP-floor raise
    // needs -- this file doesn't also have to be told twice.
    ->withPhpSets()
    ->withPreparedSets(deadCode: true);
