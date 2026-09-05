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

final class Crypto
{
    private string $keyMaterial;

    public function __construct(?string $keyMaterial = null)
    {
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new \RuntimeException('ZipQuantum requires the PHP OpenSSL extension.');
        }

        if ($keyMaterial === null) {
            $cookieKey = defined('_COOKIE_KEY_') ? (string) _COOKIE_KEY_ : '';
            $cookieIv = defined('_COOKIE_IV_') ? (string) _COOKIE_IV_ : '';
            $keyMaterial = $cookieKey . '|' . $cookieIv;
        }
        if ($keyMaterial === '' || $keyMaterial === '|') {
            throw new \RuntimeException('ZipQuantum cannot derive a credential encryption key.');
        }
        $this->keyMaterial = $keyMaterial;
    }

    /** @param mixed $value */
    public function encrypt($value): string
    {
        $plain = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt(
            $plain,
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'zipquantum-prestashop'
        );
        if ($cipher === false) {
            throw new \RuntimeException('ZipQuantum could not encrypt credentials.');
        }

        return 'zqps1:' . base64_encode(json_encode([
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($cipher),
        ], JSON_THROW_ON_ERROR));
    }

    /** @return mixed|null */
    public function decrypt(string $payload)
    {
        if (strncmp($payload, 'zqps1:', 6) !== 0) {
            return null;
        }

        try {
            $decoded = base64_decode(substr($payload, 6), true);
            if ($decoded === false) {
                return null;
            }
            $envelope = json_decode($decoded, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($envelope)) {
                return null;
            }
            $iv = base64_decode((string) ($envelope['iv'] ?? ''), true);
            $tag = base64_decode((string) ($envelope['tag'] ?? ''), true);
            $data = base64_decode((string) ($envelope['data'] ?? ''), true);
            if ($iv === false || $tag === false || $data === false) {
                return null;
            }
            $plain = openssl_decrypt(
                $data,
                'aes-256-gcm',
                $this->key(),
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                'zipquantum-prestashop'
            );
            if ($plain === false) {
                return null;
            }

            return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            return null;
        }
    }

    private function key(): string
    {
        return hash('sha256', $this->keyMaterial, true);
    }
}
