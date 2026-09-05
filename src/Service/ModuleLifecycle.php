<?php
/**
 * ZipQuantum Smart Links and QR Codes integration for PrestaShop.
 *
 * @author Xaere
 * @copyright 2026 Xaere
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ZipQuantum\PrestaShop\Service;

if (!defined('_PS_VERSION_') && !defined('ZQPS_TESTING')) {
    exit;
}

final class ModuleLifecycle
{
    public function installDatabase(): bool
    {
        $queries = [
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'zqps_association` (
                `id_association` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL,
                `object_type` VARCHAR(32) NOT NULL,
                `object_id` INT UNSIGNED NOT NULL,
                `management_mode` VARCHAR(16) NOT NULL,
                `link_id` BIGINT UNSIGNED NULL,
                `managed_fields` TEXT NOT NULL,
                `smart_link` LONGTEXT NOT NULL,
                `local_status` VARCHAR(24) NOT NULL DEFAULT "active",
                `payload_hash` CHAR(64) NOT NULL DEFAULT "",
                `last_error` TEXT NULL,
                `last_synced_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id_association`),
                UNIQUE KEY `zqps_association_object` (`id_shop`, `object_type`, `object_id`),
                KEY `zqps_association_link` (`link_id`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'zqps_queue` (
                `id_queue` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL,
                `operation` VARCHAR(40) NOT NULL,
                `object_type` VARCHAR(32) NOT NULL,
                `object_id` INT UNSIGNED NOT NULL,
                `payload_hash` CHAR(64) NOT NULL,
                `payload` LONGTEXT NOT NULL,
                `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `next_attempt_at` DATETIME NOT NULL,
                `status` VARCHAR(24) NOT NULL DEFAULT "pending",
                `last_error` TEXT NULL,
                `locked_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id_queue`),
                UNIQUE KEY `zqps_queue_dedupe` (`id_shop`, `operation`, `object_type`, `object_id`, `payload_hash`),
                KEY `zqps_queue_due` (`id_shop`, `status`, `next_attempt_at`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];
        foreach ($queries as $query) {
            if (!\Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    public function uninstallDatabase(): bool
    {
        return (bool) \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'zqps_queue`')
            && (bool) \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'zqps_association`');
    }
}
