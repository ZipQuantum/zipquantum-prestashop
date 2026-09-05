<?php
/**
 * ZipQuantum Smart Links and QR Codes integration for PrestaShop.
 *
 * @author Xaere
 * @copyright 2026 Xaere
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ZipQuantum\PrestaShop\Repository;

use ZipQuantum\PrestaShop\Support\CanonicalJson;

if (!defined('_PS_VERSION_') && !defined('ZQPS_TESTING')) {
    exit;
}

final class QueueRepository
{
    /** @param array<string, mixed> $payload */
    public function enqueue(int $shopId, string $operation, string $objectType, int $objectId, array $payload): bool
    {
        $hash = CanonicalJson::hash($payload);
        $where = '`id_shop` = ' . (int) $shopId
            . ' AND `operation` = "' . pSQL($operation) . '"'
            . ' AND `object_type` = "' . pSQL($objectType) . '"'
            . ' AND `object_id` = ' . (int) $objectId
            . ' AND `payload_hash` = "' . pSQL($hash) . '"';
        $existing = \Db::getInstance()->getValue(
            'SELECT `id_queue` FROM `' . _DB_PREFIX_ . 'zqps_queue` WHERE ' . $where
        );
        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'payload' => pSQL(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), true),
            'attempts' => 0,
            'next_attempt_at' => $now,
            'updated_at' => $now,
            'last_error' => null,
        ];
        if ($existing) {
            $data['status'] = 'pending';
            $where = '`id_queue` = ' . (int) $existing . ' AND `status` <> "processing"';

            return (bool) \Db::getInstance()->update('zqps_queue', $data, $where);
        }
        $data += [
            'id_shop' => (int) $shopId,
            'operation' => pSQL($operation),
            'object_type' => pSQL($objectType),
            'object_id' => (int) $objectId,
            'payload_hash' => pSQL($hash),
            'attempts' => 0,
            'status' => 'pending',
            'created_at' => $now,
        ];

        return (bool) \Db::getInstance()->insert('zqps_queue', $data);
    }

    /** @return array<int, array<string, mixed>> */
    public function due(int $shopId, int $limit): array
    {
        \Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'zqps_queue`'
            . ' SET `status` = "retry", `locked_at` = NULL, `next_attempt_at` = UTC_TIMESTAMP(),'
            . ' `last_error` = "worker_interrupted", `updated_at` = UTC_TIMESTAMP()'
            . ' WHERE `id_shop` = ' . (int) $shopId
            . ' AND `status` = "processing"'
            . ' AND `locked_at` < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)'
        );
        $rows = \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'zqps_queue`'
            . ' WHERE `id_shop` = ' . (int) $shopId
            . ' AND `status` IN ("pending", "retry")'
            . ' AND `next_attempt_at` <= UTC_TIMESTAMP()'
            . ' ORDER BY `id_queue` ASC LIMIT ' . (int) max(1, min(50, $limit))
        );

        return is_array($rows) ? $rows : [];
    }

    public function claim(int $queueId, string $previousStatus): bool
    {
        return (bool) \Db::getInstance()->update(
            'zqps_queue',
            ['status' => 'processing', 'locked_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')],
            '`id_queue` = ' . (int) $queueId . ' AND `status` = "' . pSQL($previousStatus) . '"'
        );
    }

    /** @param array<string, mixed> $data */
    public function update(int $queueId, array $data): bool
    {
        $allowed = ['status', 'attempts', 'next_attempt_at', 'last_error', 'locked_at', 'updated_at'];
        $safe = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $safe[$field] = is_string($data[$field]) ? pSQL($data[$field], true) : $data[$field];
            }
        }
        $safe['updated_at'] = gmdate('Y-m-d H:i:s');

        return (bool) \Db::getInstance()->update('zqps_queue', $safe, '`id_queue` = ' . (int) $queueId);
    }

    public function setStatusForShop(int $shopId, string $fromStatus, string $toStatus, ?string $error = null): bool
    {
        return (bool) \Db::getInstance()->update(
            'zqps_queue',
            [
                'status' => pSQL($toStatus),
                'attempts' => 0,
                'last_error' => $error === null ? null : pSQL($error, true),
                'next_attempt_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ],
            '`id_shop` = ' . (int) $shopId . ' AND `status` = "' . pSQL($fromStatus) . '"'
        );
    }

    public function blockActive(int $shopId, string $error): bool
    {
        return (bool) \Db::getInstance()->update(
            'zqps_queue',
            ['status' => 'blocked', 'last_error' => pSQL($error, true), 'updated_at' => gmdate('Y-m-d H:i:s')],
            '`id_shop` = ' . (int) $shopId . ' AND `status` IN ("pending", "retry", "processing")'
        );
    }

    public function quarantineAll(int $shopId): bool
    {
        return (bool) \Db::getInstance()->update(
            'zqps_queue',
            ['status' => 'quarantined', 'last_error' => 'cloned_installation', 'updated_at' => gmdate('Y-m-d H:i:s')],
            '`id_shop` = ' . (int) $shopId . ' AND `status` <> "complete"'
        );
    }

    public function cancelObject(int $shopId, string $objectType, int $objectId): bool
    {
        return (bool) \Db::getInstance()->update(
            'zqps_queue',
            ['status' => 'cancelled', 'last_error' => 'prestashop_object_deleted', 'updated_at' => gmdate('Y-m-d H:i:s')],
            '`id_shop` = ' . (int) $shopId
            . ' AND `object_type` = "' . pSQL($objectType) . '"'
            . ' AND `object_id` = ' . (int) $objectId
            . ' AND `status` <> "complete"'
        );
    }

    /** @return array<string, int> */
    public function stats(int $shopId): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT `status`, COUNT(*) AS `total` FROM `' . _DB_PREFIX_ . 'zqps_queue`'
            . ' WHERE `id_shop` = ' . (int) $shopId . ' GROUP BY `status`'
        );
        $stats = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $stats[(string) $row['status']] = (int) $row['total'];
        }

        return $stats;
    }
}
