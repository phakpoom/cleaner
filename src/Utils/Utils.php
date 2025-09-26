<?php

declare(strict_types=1);

namespace Utils;

final class Utils
{
    public static function formatBytes($size, $precision = 2): string
    {
        $base = log($size, 1024);
        $suffixes = array('', 'KB', 'MB', 'GB', 'TB');

        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }
}
