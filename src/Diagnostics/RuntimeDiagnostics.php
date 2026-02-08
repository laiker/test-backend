<?php

namespace App\Diagnostics;

final class RuntimeDiagnostics
{
    private static array $counters = [];
    private static array $samples = [];

    public static function reset(): void
    {
        self::$counters = [];
        self::$samples = [];
    }

    public static function increment(string $key, int $by = 1): void
    {
        self::$counters[$key] = (self::$counters[$key] ?? 0) + $by;
    }

    public static function sample(string $key, string $value, int $maxItems = 5): void
    {
        $values = self::$samples[$key] ?? [];
        if (count($values) >= $maxItems || in_array($value, $values, true)) {
            return;
        }

        $values[] = $value;
        self::$samples[$key] = $values;
    }

    public static function counter(string $key): int
    {
        return self::$counters[$key] ?? 0;
    }

    public static function sampleList(string $key): array
    {
        return self::$samples[$key] ?? [];
    }
}
