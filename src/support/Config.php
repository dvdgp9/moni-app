<?php
declare(strict_types=1);

namespace Moni\Support;

final class Config
{
    private static array $config = [];

    public static function init(array $config): void
    {
        self::$config = $config;
    }

    public static function get(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $value = self::$config;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function merge(array $overrides): void
    {
        self::$config = self::arrayMergeRecursiveDistinct(self::$config, $overrides);
    }

    private static function arrayMergeRecursiveDistinct(array $array1, array $array2): array
    {
        $merged = $array1;
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = self::arrayMergeRecursiveDistinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }
}
