<?php

namespace App\Tests\Support;

trait PrettyPhpUnitOutput
{
    protected function info(string $message): void
    {
        if (getenv('PHPUNIT_VERBOSE_INFO') === '1') {
            fwrite(STDERR, PHP_EOL . 'INFO: ' . $message . PHP_EOL);
        }
    }

    protected function success(string $message): void
    {
        if (getenv('PHPUNIT_VERBOSE_INFO') === '1') {
            fwrite(STDERR, 'OK: ' . $message . PHP_EOL);
        }
    }

    protected function errorBlock(string $title, array $lines = []): string
    {
        $result = 'ERROR: ' . $title;

        foreach ($lines as $line) {
            $result .= PHP_EOL . ' - ' . $line;
        }

        return $result;
    }
}
