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

final class CanonicalJson
{
    /** @param array<mixed> $payload */
    public static function hash(array $payload): string
    {
        return hash('sha256', json_encode(
            self::canonicalize($payload),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    /** @param mixed $value */
    public static function canonicalize($value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }
}
