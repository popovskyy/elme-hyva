<?php

declare(strict_types=1);

/**
 * PHPUnit Allure extension config for runs from the repo root
 * (`vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist …`).
 * Magento's own copy under `dev/tests/unit/allure/` is used when CWD is that folder.
 */

$outputDirectory = dirname(__DIR__) . '/dev/tests/unit/var/allure-results';
if (!is_dir($outputDirectory)) {
    mkdir($outputDirectory, 0755, true);
}

return [
    'outputDirectory' => $outputDirectory,
    'setupHook' => static function () use ($outputDirectory): void {
        foreach (scandir($outputDirectory) ?: [] as $file) {
            $filePath = $outputDirectory . DIRECTORY_SEPARATOR . $file;
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }
    },
];
