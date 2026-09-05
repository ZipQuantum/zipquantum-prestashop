<?php
/**
 * ZipQuantum Smart Links and QR Codes integration for PrestaShop.
 *
 * @author Xaere
 * @copyright 2026 Xaere
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ZipQuantum\PrestaShop\Api;

use ZipQuantum\PrestaShop\Storage\ConfigurationStore;

if (!defined('_PS_VERSION_') && !defined('ZQPS_TESTING')) {
    exit;
}

final class ApiClient
{
    public const CLIENT_ID = 'zipquantum-prestashop';
    public const RESOURCE = 'https://a.zq.tn/api';
    public const DEFAULT_BASE_URI = 'https://a.zq.tn';

    private ConfigurationStore $store;
    private string $baseUri;
    private string $moduleVersion;
    private string $shopUrl;

    public function __construct(
        ConfigurationStore $store,
        string $moduleVersion = '1.0.0',
        string $baseUri = self::DEFAULT_BASE_URI,
        string $shopUrl = '',
    ) {
        $this->store = $store;
        $this->moduleVersion = $moduleVersion;
        $this->baseUri = rtrim($baseUri, '/');
        $this->shopUrl = $shopUrl;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function publicRequest(string $method, string $path, array $body = [], array $headers = []): array
    {
        return $this->requestRaw($method, $path, $body, $headers);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $body = [], array $headers = []): array
    {
        $credentials = $this->store->getSecret(ConfigurationStore::CREDENTIALS, []);
        if (!is_array($credentials) || empty($credentials['access_token'])) {
            throw new ApiException('Reconnect ZipQuantum to continue.', 401, 'reconnect_required');
        }
        $headers['Authorization'] = 'Bearer ' . (string) $credentials['access_token'];
        $headers['X-ZQ-Site-URL'] = $this->shopUrl;

        try {
            return $this->requestRaw($method, $path, $body, $headers);
        } catch (ApiException $error) {
            if ($error->status() !== 401 || empty($credentials['refresh_token'])) {
                throw $error;
            }
            $tokens = $this->refresh((string) $credentials['refresh_token']);
            $headers['Authorization'] = 'Bearer ' . (string) $tokens['access_token'];

            return $this->requestRaw($method, $path, $body, $headers);
        }
    }

    /** @return array<string, mixed> */
    public function refresh(string $refreshToken): array
    {
        $tokens = $this->requestRaw('POST', '/api/v1/integrations/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => self::CLIENT_ID,
            'resource' => self::RESOURCE,
            'refresh_token' => $refreshToken,
        ], []);
        if (empty($tokens['access_token']) || empty($tokens['refresh_token']) || empty($tokens['installation_id'])) {
            throw new ApiException('ZipQuantum returned an invalid token response.', 502, 'invalid_token_response');
        }
        $this->store->setSecret(ConfigurationStore::CREDENTIALS, $tokens);

        return $tokens;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function requestRaw(string $method, string $path, array $body, array $headers): array
    {
        if (!function_exists('curl_init')) {
            throw new ApiException('ZipQuantum requires the PHP cURL extension.', 0, 'curl_unavailable');
        }
        $url = $this->baseUri . '/' . ltrim($path, '/');
        if (!str_starts_with($url, self::DEFAULT_BASE_URI . '/')) {
            throw new ApiException('The ZipQuantum API endpoint is not allowed.', 0, 'invalid_api_endpoint');
        }

        $responseHeaders = [];
        $requestHeaders = array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'ZipQuantum-PrestaShop/' . $this->moduleVersion,
        ], $headers);
        $headerLines = [];
        foreach ($requestHeaders as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new ApiException('Unable to initialize the ZipQuantum API client.');
        }
        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                unset($curl);
                $length = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            },
        ];
        if ($body !== [] || in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
            $options[CURLOPT_POSTFIELDS] = json_encode(
                $body,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        }
        curl_setopt_array($handle, $options);
        $content = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($content === false) {
            throw new ApiException($error !== '' ? $error : 'ZipQuantum network request failed.');
        }
        try {
            $data = $content === '' ? [] : json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            $data = [];
        }
        if (!is_array($data)) {
            $data = [];
        }
        if ($status < 200 || $status >= 300) {
            $message = isset($data['message']) && is_string($data['message']) ? $data['message'] : 'ZipQuantum request failed.';
            $code = isset($data['code']) && is_string($data['code']) ? $data['code'] : 'http_' . $status;
            throw new ApiException($message, $status, $code, $responseHeaders);
        }

        return $data;
    }
}
