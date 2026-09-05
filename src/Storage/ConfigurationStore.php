<?php
/**
 * ZipQuantum Smart Links and QR Codes integration for PrestaShop.
 *
 * @author Xaere
 * @copyright 2026 Xaere
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ZipQuantum\PrestaShop\Storage;

use ZipQuantum\PrestaShop\Security\Crypto;

if (!defined('_PS_VERSION_') && !defined('ZQPS_TESTING')) {
    exit;
}

final class ConfigurationStore
{
    public const MANAGED_SUBDOMAIN = 'ZQPS_MANAGED_SUBDOMAIN';
    public const CUSTOM_DOMAIN = 'ZQPS_CUSTOM_DOMAIN';
    public const AUTO_CREATE = 'ZQPS_AUTO_CREATE';
    public const OBJECT_TYPES = 'ZQPS_OBJECT_TYPES';
    public const PROMOTION_DESTINATION = 'ZQPS_PROMOTION_DEST';
    public const INSTALLATION = 'ZQPS_INSTALLATION';
    public const CREDENTIALS = 'ZQPS_CREDENTIALS';
    public const OAUTH_PENDING = 'ZQPS_OAUTH_PENDING';
    public const CONTEXT = 'ZQPS_CONTEXT';
    public const STATE = 'ZQPS_STATE';
    public const CRON_TOKEN = 'ZQPS_CRON_TOKEN';

    private int $shopId;
    private int $shopGroupId;
    private Crypto $crypto;

    public function __construct(int $shopId = 0, int $shopGroupId = 0, ?Crypto $crypto = null)
    {
        $this->shopId = $shopId;
        $this->shopGroupId = $shopGroupId;
        $this->crypto = $crypto ?? new Crypto();
    }

    public function shopId(): int
    {
        return $this->shopId;
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        $types = $this->getJson(self::OBJECT_TYPES, ['product', 'category', 'promotion']);

        return [
            'managed_subdomain' => (string) $this->get(self::MANAGED_SUBDOMAIN, ''),
            'custom_domain' => (string) $this->get(self::CUSTOM_DOMAIN, ''),
            'auto_create' => (bool) $this->get(self::AUTO_CREATE, false),
            'object_types' => is_array($types) ? array_values($types) : [],
            'promotion_destination' => (string) $this->get(self::PROMOTION_DESTINATION, '/order'),
        ];
    }

    /** @param mixed $default @return mixed */
    public function get(string $key, $default = null)
    {
        $value = \Configuration::get($key, null, $this->shopGroupId, $this->shopId);

        return $value === false ? $default : $value;
    }

    /** @param mixed $value */
    public function set(string $key, $value): bool
    {
        return (bool) \Configuration::updateValue($key, $value, false, $this->shopGroupId, $this->shopId);
    }

    public function delete(string $key): bool
    {
        if (method_exists('\Configuration', 'deleteFromContext')) {
            return (bool) \Configuration::deleteFromContext($key);
        }

        return (bool) \Db::getInstance()->delete(
            'configuration',
            '`name` = "' . pSQL($key) . '"'
            . ' AND `id_shop_group` = ' . $this->shopGroupId
            . ' AND `id_shop` = ' . $this->shopId
        );
    }

    /** @param mixed $value */
    public function setJson(string $key, $value): bool
    {
        return $this->set($key, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @param mixed $default @return mixed */
    public function getJson(string $key, $default = null)
    {
        $value = $this->get($key);
        if (!is_string($value) || $value === '') {
            return $default;
        }
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            return $default;
        }
    }

    /** @param mixed $value */
    public function setSecret(string $key, $value): bool
    {
        return $this->set($key, $this->crypto->encrypt($value));
    }

    /** @param mixed $default @return mixed */
    public function getSecret(string $key, $default = null)
    {
        $value = $this->get($key);
        if (!is_string($value) || $value === '') {
            return $default;
        }
        $plain = $this->crypto->decrypt($value);

        return $plain === null ? $default : $plain;
    }

    public function installationUuid(): string
    {
        $installation = $this->getJson(self::INSTALLATION, []);
        if (!is_array($installation) || empty($installation['local_uuid'])) {
            $installation = [
                'local_uuid' => $this->uuidV4(),
                'created_at' => gmdate('c'),
            ];
            $this->setJson(self::INSTALLATION, $installation);
        }

        return (string) $installation['local_uuid'];
    }

    public function createNewInstallation(): string
    {
        $old = $this->installationUuid();
        $new = $this->uuidV4();
        $this->setJson(self::INSTALLATION, [
            'local_uuid' => $new,
            'created_at' => gmdate('c'),
            'cloned_from_uuid' => $old,
            'associations_status' => 'quarantined',
        ]);
        $this->delete(self::CREDENTIALS);
        $this->delete(self::CONTEXT);

        return $new;
    }

    public function cronToken(): string
    {
        $token = $this->getSecret(self::CRON_TOKEN, '');
        if (!is_string($token) || strlen($token) < 43) {
            $token = $this->randomUrlSafe(48);
            $this->setSecret(self::CRON_TOKEN, $token);
        }

        return $token;
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0F) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function randomUrlSafe(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
