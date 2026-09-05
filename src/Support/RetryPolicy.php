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

final class RetryPolicy
{
    private const DELAYS = [60, 300, 1800, 7200, 43200];

    public static function delayForAttempt(int $attempt, ?int $retryAfter = null, int $jitter = 0): ?int
    {
        if ($attempt < 1 || $attempt > count(self::DELAYS)) {
            return null;
        }
        $delay = $retryAfter !== null ? max(1, $retryAfter) : self::DELAYS[$attempt - 1];

        return $delay + max(0, $jitter);
    }

    public static function retryAfterSeconds(string $value, ?int $now = null): int
    {
        if (is_numeric($value)) {
            return max(1, (int) $value);
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return 60;
        }

        return max(1, $timestamp - ($now ?? time()));
    }
}
