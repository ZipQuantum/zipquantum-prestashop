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
use ZipQuantum\PrestaShop\Api\ApiException;
use ZipQuantum\PrestaShop\Storage\ConfigurationStore;

if (!defined('_PS_VERSION_') && !defined('ZQPS_TESTING')) {
    exit;
}

final class OAuthService
{
    private ApiClient $api;
    private ConfigurationStore $store;
    private string $shopUrl;

    public function __construct(ApiClient $api, ConfigurationStore $store, string $shopUrl)
    {
        $this->api = $api;
        $this->store = $store;
        $this->shopUrl = $shopUrl;
    }

    /** @return array<string, mixed> */
    public function start(string $intent = 'connect'): array
    {
        if (!in_array($intent, ['connect', 'move', 'reconnect'], true)) {
            $intent = 'connect';
        }
        $verifier = $this->randomUrlSafe(64);
        $state = $this->randomUrlSafe(48);
        $nonce = $this->randomUrlSafe(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $response = $this->api->publicRequest('POST', '/api/v1/integrations/prestashop/handshakes', [
            'client_id' => ApiClient::CLIENT_ID,
            'installation_uuid' => $this->store->installationUuid(),
            'installation_nonce' => $nonce,
            'home_url' => $this->shopUrl,
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'intent' => $intent,
        ]);
        $handshakeId = (string) ($response['handshake_id'] ?? '');
        $pollingSecret = (string) ($response['polling_secret'] ?? '');
        $authorizationUrl = (string) ($response['authorization_url'] ?? '');
        $parts = parse_url($authorizationUrl);
        if (
            !is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== 'a.zq.tn'
            || isset($parts['port'])
            || !str_starts_with((string) ($parts['path'] ?? ''), '/integrations/prestashop/authorize/')
            || !preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i', $handshakeId)
            || strlen($pollingSecret) < 32
        ) {
            throw new ApiException('ZipQuantum returned an invalid handshake response.', 502, 'invalid_handshake_response');
        }
        $this->store->setSecret(ConfigurationStore::OAUTH_PENDING, [
            'handshake_id' => $handshakeId,
            'polling_secret' => $pollingSecret,
            'state' => $state,
            'code_verifier' => $verifier,
            'expires_at' => time() + (int) ($response['expires_in'] ?? 600),
        ]);

        return [
            'authorization_url' => $authorizationUrl,
            'interval' => max(3, (int) ($response['interval'] ?? 3)),
        ];
    }

    /** @return array<string, mixed> */
    public function poll(): array
    {
        $pending = $this->store->getSecret(ConfigurationStore::OAUTH_PENDING, []);
        if (!is_array($pending) || empty($pending['handshake_id']) || empty($pending['polling_secret'])) {
            throw new ApiException('Start the ZipQuantum connection again.', 410, 'handoff_missing');
        }
        if (time() >= (int) ($pending['expires_at'] ?? 0)) {
            $this->store->delete(ConfigurationStore::OAUTH_PENDING);
            throw new ApiException('The connection request expired.', 410, 'handoff_expired');
        }
        $poll = $this->api->publicRequest(
            'POST',
            '/api/v1/integrations/prestashop/handshakes/' . rawurlencode((string) $pending['handshake_id']) . '/poll',
            ['polling_secret' => (string) $pending['polling_secret']]
        );
        if (($poll['status'] ?? '') !== 'authorized') {
            return ['status' => 'pending'];
        }
        if (empty($poll['state']) || !hash_equals((string) $pending['state'], (string) $poll['state'])) {
            throw new ApiException('OAuth state verification failed.', 400, 'invalid_state');
        }
        $tokens = $this->api->publicRequest('POST', '/api/v1/integrations/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => ApiClient::CLIENT_ID,
            'resource' => ApiClient::RESOURCE,
            'code' => (string) ($poll['authorization_code'] ?? ''),
            'code_verifier' => (string) ($pending['code_verifier'] ?? ''),
        ]);
        if (empty($tokens['access_token']) || empty($tokens['refresh_token']) || empty($tokens['installation_id'])) {
            throw new ApiException('ZipQuantum returned an invalid token response.', 502, 'invalid_token_response');
        }
        $this->store->setSecret(ConfigurationStore::CREDENTIALS, $tokens);
        $this->store->delete(ConfigurationStore::OAUTH_PENDING);
        $this->store->setJson(ConfigurationStore::STATE, ['identity_mismatch' => false, 'connected_at' => gmdate('c')]);
        $context = $this->api->request('GET', '/api/v1/integration/context');
        $this->store->setJson(ConfigurationStore::CONTEXT, $context);

        return ['status' => 'connected', 'context' => $context];
    }

    public function disconnect(): void
    {
        $credentials = $this->store->getSecret(ConfigurationStore::CREDENTIALS, []);
        if (is_array($credentials) && !empty($credentials['access_token'])) {
            try {
                $this->api->publicRequest('POST', '/api/v1/integrations/oauth/revoke', [
                    'token' => (string) $credentials['access_token'],
                ]);
            } catch (\Throwable $error) {
                // Local disconnect remains available during a service outage.
            }
        }
        $this->store->delete(ConfigurationStore::CREDENTIALS);
        $this->store->delete(ConfigurationStore::CONTEXT);
    }

    private function randomUrlSafe(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
