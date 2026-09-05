<?php
/**
 * ZipQuantum Smart Links and QR Codes integration for PrestaShop.
 *
 * @author Xaere
 * @copyright 2026 Xaere
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ZipQuantum\PrestaShop\Support;

if (!defined('_PS_VERSION_') && !defined('ZQPS_TESTING')) {
    exit;
}

final class LocalPath
{
    public static function normalize(string $path, string $fallback = '/order'): string
    {
        $path = trim($path);
        $decoded = rawurldecode($path);
        if (
            $path === ''
            || str_starts_with($path, '//')
            || str_starts_with($decoded, '//')
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $decoded)
            || preg_match('/[\x00-\x1F\x7F\\\\]/', $path)
            || preg_match('/[\x00-\x1F\x7F\\\\]/', $decoded)
        ) {
            return $fallback;
        }
        $path = '/' . ltrim($path, '/');
        if (str_contains($path, '..') || str_contains($decoded, '..')) {
            return $fallback;
        }

        return $path;
    }
}
