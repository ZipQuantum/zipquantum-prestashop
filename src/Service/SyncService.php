<?php
/**
 * ZipQuantum Smart Links and QR Codes integration for PrestaShop.
 *
 * @author Xaere
 * @copyright 2026 Xaere
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ZipQuantum\PrestaShop\Service;

use ZipQuantum\PrestaShop\Api\ApiClient;
use ZipQuantum\PrestaShop\Domain\ObjectPayloadFactory;
use ZipQuantum\PrestaShop\Repository\AssociationRepository;
use ZipQuantum\PrestaShop\Security\SmartLinkSanitizer;
use ZipQuantum\PrestaShop\Storage\ConfigurationStore;
use ZipQuantum\PrestaShop\Support\CanonicalJson;

if (!defined('_PS_VERSION_') && !defined('ZQPS_TESTING')) {
    exit;
}

final class SyncService
{
    private ApiClient $api;
    private ConfigurationStore $store;
    private AssociationRepository $associations;
    private ObjectPayloadFactory $payloads;

    public function __construct(
        ApiClient $api,
        ConfigurationStore $store,
        AssociationRepository $associations,
        ObjectPayloadFactory $payloads,
    ) {
        $this->api = $api;
        $this->store = $store;
        $this->associations = $associations;
        $this->payloads = $payloads;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function sync(string $objectType, int $objectId, array $payload = []): array
    {
        if ($payload === []) {
            $payload = $this->payloads->build($objectType, $objectId);
        }
        $credentials = $this->store->getSecret(ConfigurationStore::CREDENTIALS, []);
        if (!is_array($credentials) || empty($credentials['installation_id'])) {
            throw new \ZipQuantum\PrestaShop\Api\ApiException('Reconnect ZipQuantum to synchronize content.', 401, 'reconnect_required');
        }
        $hash = CanonicalJson::hash($payload);
        $key = implode(':', [
            'ps',
            (string) $credentials['installation_id'],
            $objectType,
            (string) $objectId,
            $hash,
        ]);
        $result = $this->api->request('POST', '/api/v1/integration-links/sync', $payload, ['Idempotency-Key' => $key]);
        $smartLink = is_array($result['smart_link'] ?? null)
            ? SmartLinkSanitizer::sanitize($result['smart_link'])
            : [];
        $this->associations->save($this->store->shopId(), $objectType, $objectId, [
            'management_mode' => (string) $payload['management_mode'],
            'link_id' => (int) ($smartLink['id'] ?? $payload['link_id'] ?? 0),
            'managed_fields' => $payload['managed_fields'] ?? [],
            'smart_link' => $smartLink,
            'local_status' => 'active',
            'payload_hash' => $hash,
            'last_error' => null,
            'last_synced_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return $result;
    }

    public function refreshAnalytics(): int
    {
        $updated = 0;
        $page = 1;
        do {
            $response = $this->api->request('GET', '/api/v1/integration-links?per_page=100&page=' . $page);
            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            $updated += $this->associations->refreshFromRemote($this->store->shopId(), $data);
            $lastPage = max(1, (int) ($response['meta']['last_page'] ?? 1));
            ++$page;
        } while ($page <= $lastPage && $page <= 20);

        return $updated;
    }
}
