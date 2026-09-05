<?php
/**
 * ZipQuantum Smart Links and QR Codes integration for PrestaShop.
 *
 * @author Xaere
 * @copyright 2026 Xaere
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ZipQuantum\PrestaShop\Service;

use ZipQuantum\PrestaShop\Api\ApiException;
use ZipQuantum\PrestaShop\Repository\QueueRepository;
use ZipQuantum\PrestaShop\Storage\ConfigurationStore;
use ZipQuantum\PrestaShop\Support\RetryPolicy;

if (!defined('_PS_VERSION_') && !defined('ZQPS_TESTING')) {
    exit;
}

final class QueueService
{
    private QueueRepository $queue;
    private SyncService $sync;
    private ConfigurationStore $store;

    public function __construct(QueueRepository $queue, SyncService $sync, ConfigurationStore $store)
    {
        $this->queue = $queue;
        $this->sync = $sync;
        $this->store = $store;
    }

    /** @param array<string, mixed> $payload */
    public function enqueue(string $objectType, int $objectId, array $payload): bool
    {
        return $this->queue->enqueue($this->store->shopId(), 'sync', $objectType, $objectId, $payload);
    }

    /** @return array{processed:int,complete:int,retry:int,failed:int,blocked:int} */
    public function process(int $limit = 10): array
    {
        $summary = ['processed' => 0, 'complete' => 0, 'retry' => 0, 'failed' => 0, 'blocked' => 0];
        $credentials = $this->store->getSecret(ConfigurationStore::CREDENTIALS, []);
        if (!is_array($credentials) || empty($credentials['access_token'])) {
            return $summary;
        }

        foreach ($this->queue->due($this->store->shopId(), $limit) as $row) {
            if (!$this->queue->claim((int) $row['id_queue'], (string) $row['status'])) {
                continue;
            }
            ++$summary['processed'];
            try {
                $payload = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($payload)) {
                    throw new \RuntimeException('Queue payload is invalid.');
                }
                $this->sync->sync((string) $row['object_type'], (int) $row['object_id'], $payload);
                $this->queue->update((int) $row['id_queue'], [
                    'status' => 'complete',
                    'last_error' => null,
                    'locked_at' => null,
                ]);
                ++$summary['complete'];
            } catch (ApiException $error) {
                $state = $this->handleApiError($row, $error);
                ++$summary[$state];
            } catch (\Throwable $error) {
                $state = $this->scheduleRetry($row, $error->getMessage());
                ++$summary[$state];
            }
        }

        return $summary;
    }

    public function retryFailed(): bool
    {
        return $this->queue->setStatusForShop($this->store->shopId(), 'failed', 'pending');
    }

    public function resumeBlocked(): bool
    {
        $state = $this->store->getJson(ConfigurationStore::STATE, []);
        if (is_array($state) && !empty($state['identity_mismatch'])) {
            return false;
        }

        return $this->queue->setStatusForShop($this->store->shopId(), 'blocked', 'pending');
    }

    /** @param array<string, mixed> $row */
    private function handleApiError(array $row, ApiException $error): string
    {
        if ($error->status() === 409 && $error->apiCode() === 'installation_identity_mismatch') {
            $this->queue->blockActive($this->store->shopId(), 'installation_identity_mismatch');
            $this->store->setJson(ConfigurationStore::STATE, [
                'identity_mismatch' => true,
                'detected_at' => gmdate('c'),
            ]);

            return 'blocked';
        }
        if ($error->status() === 401) {
            $this->queue->update((int) $row['id_queue'], [
                'status' => 'blocked',
                'last_error' => 'reconnect_required',
                'locked_at' => null,
            ]);

            return 'blocked';
        }
        if ($error->status() === 422) {
            $this->queue->update((int) $row['id_queue'], [
                'status' => 'failed',
                'last_error' => $error->getMessage(),
                'locked_at' => null,
            ]);

            return 'failed';
        }
        $retryAfter = null;
        if ($error->status() === 429) {
            $headers = $error->responseHeaders();
            if (isset($headers['retry-after'])) {
                $retryAfter = RetryPolicy::retryAfterSeconds($headers['retry-after']);
            }
        }

        return $this->scheduleRetry($row, $error->getMessage(), $retryAfter);
    }

    /** @param array<string, mixed> $row */
    private function scheduleRetry(array $row, string $message, ?int $retryAfter = null): string
    {
        $attempt = (int) $row['attempts'] + 1;
        $policyDelay = RetryPolicy::delayForAttempt($attempt);
        if ($policyDelay === null) {
            $this->queue->update((int) $row['id_queue'], [
                'status' => 'failed',
                'attempts' => $attempt,
                'last_error' => $message,
                'locked_at' => null,
            ]);

            return 'failed';
        }
        $base = $retryAfter ?? $policyDelay;
        $jitter = random_int(0, max(3, (int) floor($base * 0.15)));
        $delay = RetryPolicy::delayForAttempt($attempt, $base, $jitter) ?? $base;
        $this->queue->update((int) $row['id_queue'], [
            'status' => 'retry',
            'attempts' => $attempt,
            'next_attempt_at' => gmdate('Y-m-d H:i:s', time() + $delay),
            'last_error' => $message,
            'locked_at' => null,
        ]);

        return 'retry';
    }
}
