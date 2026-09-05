<?php
/**
 * ZipQuantum Smart Links and QR Codes integration for PrestaShop.
 *
 * @author Xaere
 * @copyright 2026 Xaere
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ZipQuantum\PrestaShop\Api;

if (!defined('_PS_VERSION_') && !defined('ZQPS_TESTING')) {
    exit;
}

final class ApiException extends \RuntimeException
{
    /** @var array<string, string> */
    private array $responseHeaders;
    private int $status;
    private string $apiCode;

    /**
     * @param array<string, string> $responseHeaders
     */
    public function __construct(string $message, int $status = 0, string $apiCode = 'network_error', array $responseHeaders = [])
    {
        parent::__construct($message);
        $this->status = $status;
        $this->apiCode = $apiCode;
        $this->responseHeaders = $responseHeaders;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function apiCode(): string
    {
        return $this->apiCode;
    }

    /** @return array<string, string> */
    public function responseHeaders(): array
    {
        return $this->responseHeaders;
    }
}
