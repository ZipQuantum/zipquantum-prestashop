<?php
/**
 * ZipQuantum Smart Links and QR Codes integration for PrestaShop.
 *
 * @author Xaere
 * @copyright 2026 Xaere
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ZipQuantum\PrestaShop\Security;

if (!defined('_PS_VERSION_') && !defined('ZQPS_TESTING')) {
    exit;
}

final class SmartLinkSanitizer
{
    private const MAX_QR_BYTES = 524288;

    /** @param array<string, mixed> $smartLink @return array<string, mixed> */
    public static function sanitize(array $smartLink): array
    {
        if (isset($smartLink['id'])) {
            $id = (int) $smartLink['id'];
            if ($id > 0) {
                $smartLink['id'] = $id;
            } else {
                unset($smartLink['id']);
            }
        }

        if (isset($smartLink['short_link'])) {
            $shortLink = self::httpsUrl((string) $smartLink['short_link']);
            if ($shortLink === null) {
                unset($smartLink['short_link']);
            } else {
                $smartLink['short_link'] = $shortLink;
            }
        }

        if (isset($smartLink['clicks'])) {
            $smartLink['clicks'] = max(0, (int) $smartLink['clicks']);
        }

        if (isset($smartLink['qr'])) {
            $qr = self::qrDataUri((string) $smartLink['qr']);
            if ($qr === null) {
                unset($smartLink['qr']);
            } else {
                $smartLink['qr'] = $qr;
            }
        }

        return $smartLink;
    }

    private static function httpsUrl(string $url): ?string
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $parts = parse_url($url);
        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        return $url;
    }

    private static function qrDataUri(string $uri): ?string
    {
        if (!preg_match('#^data:image/(png|jpeg|svg\+xml);base64,([a-z0-9+/=\r\n]+)$#i', $uri, $matches)) {
            return null;
        }
        $bytes = base64_decode($matches[2], true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_QR_BYTES) {
            return null;
        }

        $type = strtolower($matches[1]);
        if ($type === 'png' && !str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return null;
        }
        if ($type === 'jpeg' && !str_starts_with($bytes, "\xff\xd8\xff")) {
            return null;
        }
        if ($type === 'svg+xml') {
            if (!preg_match('/<svg\b/i', $bytes)) {
                return null;
            }
            if (preg_match('/<!DOCTYPE|<!ENTITY|<script\b|<foreignObject\b|\bon[a-z]+\s*=/i', $bytes)) {
                return null;
            }
            preg_match_all('/(?:href|xlink:href)\s*=\s*(["\'])(.*?)\1/is', $bytes, $references);
            foreach ($references[2] as $reference) {
                if (!preg_match('#^data:image/(png|jpeg|webp);base64,[a-z0-9+/=]+$#i', (string) $reference)) {
                    return null;
                }
            }
            preg_match_all('/url\s*\(\s*(["\']?)(.*?)\1\s*\)/is', $bytes, $urls);
            foreach ($urls[2] as $reference) {
                if (!preg_match('/^#[a-z0-9_:.-]+$/i', (string) $reference)) {
                    return null;
                }
            }
        }

        return $uri;
    }
}
