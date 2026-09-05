<?php
/**
 * ZipQuantum Smart Links and QR Codes integration for PrestaShop.
 *
 * @author Xaere
 * @copyright 2026 Xaere
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ZipQuantum\PrestaShop\Repository;

use ZipQuantum\PrestaShop\Security\SmartLinkSanitizer;

if (!defined('_PS_VERSION_') && !defined('ZQPS_TESTING')) {
    exit;
}

final class AssociationRepository
{
    /** @return array<string, mixed> */
    public function find(int $shopId, string $objectType, int $objectId): array
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'zqps_association`'
            . ' WHERE `id_shop` = ' . (int) $shopId
            . ' AND `object_type` = "' . pSQL($objectType) . '"'
            . ' AND `object_id` = ' . (int) $objectId;
        $row = \Db::getInstance()->getRow($sql);
        if (!is_array($row)) {
            return [];
        }
        $row['managed_fields'] = $this->decode((string) ($row['managed_fields'] ?? '[]'));
        $row['smart_link'] = $this->decode((string) ($row['smart_link'] ?? '{}'));

        return $row;
    }

    /** @param array<string, mixed> $association */
    public function save(int $shopId, string $objectType, int $objectId, array $association): bool
    {
        $current = $this->find($shopId, $objectType, $objectId);
        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'id_shop' => (int) $shopId,
            'object_type' => pSQL($objectType),
            'object_id' => (int) $objectId,
            'management_mode' => pSQL((string) ($association['management_mode'] ?? 'managed')),
            'link_id' => isset($association['link_id']) ? (int) $association['link_id'] : null,
            'managed_fields' => pSQL(json_encode($association['managed_fields'] ?? [], JSON_THROW_ON_ERROR), true),
            'smart_link' => pSQL(json_encode($association['smart_link'] ?? [], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), true),
            'local_status' => pSQL((string) ($association['local_status'] ?? 'active')),
            'payload_hash' => pSQL((string) ($association['payload_hash'] ?? '')),
            'last_error' => isset($association['last_error']) ? pSQL((string) $association['last_error'], true) : null,
            'last_synced_at' => $association['last_synced_at'] ?? null,
            'updated_at' => $now,
        ];

        if ($current !== []) {
            return (bool) \Db::getInstance()->update('zqps_association', $data, '`id_association` = ' . (int) $current['id_association']);
        }
        $data['created_at'] = $now;

        return (bool) \Db::getInstance()->insert('zqps_association', $data);
    }

    public function deleteLocal(int $shopId, string $objectType, int $objectId): bool
    {
        return (bool) \Db::getInstance()->delete(
            'zqps_association',
            '`id_shop` = ' . (int) $shopId
            . ' AND `object_type` = "' . pSQL($objectType) . '"'
            . ' AND `object_id` = ' . (int) $objectId
        );
    }

    public function quarantineAll(int $shopId): bool
    {
        return (bool) \Db::getInstance()->update(
            'zqps_association',
            ['local_status' => 'quarantined', 'updated_at' => gmdate('Y-m-d H:i:s')],
            '`id_shop` = ' . (int) $shopId
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $shopId, int $limit = 50): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'zqps_association`'
            . ' WHERE `id_shop` = ' . (int) $shopId
            . ' ORDER BY `updated_at` DESC LIMIT ' . (int) max(1, min(100, $limit))
        );
        if (!is_array($rows)) {
            return [];
        }
        foreach ($rows as &$row) {
            $row['managed_fields'] = $this->decode((string) ($row['managed_fields'] ?? '[]'));
            $row['smart_link'] = $this->decode((string) ($row['smart_link'] ?? '{}'));
        }
        unset($row);

        return $rows;
    }

    /** @param array<int, array<string, mixed>> $remote */
    public function refreshFromRemote(int $shopId, array $remote): int
    {
        $updated = 0;
        foreach ($remote as $item) {
            if (!is_array($item) || empty($item['object_type']) || empty($item['object_id']) || !isset($item['smart_link'])) {
                continue;
            }
            $current = $this->find($shopId, (string) $item['object_type'], (int) $item['object_id']);
            if ($current === []) {
                continue;
            }
            $remoteLink = is_array($item['smart_link'])
                ? SmartLinkSanitizer::sanitize($item['smart_link'])
                : [];
            $currentLink = is_array($current['smart_link'] ?? null) ? $current['smart_link'] : [];
            $current['smart_link'] = array_replace($currentLink, $remoteLink);
            $current['last_synced_at'] = $item['last_synced_at'] ?? $current['last_synced_at'];
            if ($this->save($shopId, (string) $item['object_type'], (int) $item['object_id'], $current)) {
                ++$updated;
            }
        }

        return $updated;
    }

    /** @return array<mixed> */
    private function decode(string $value): array
    {
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $error) {
            return [];
        }
    }
}
