<?php
/**
 * ZipQuantum Smart Links and QR Codes integration for PrestaShop.
 *
 * @author Xaere
 * @copyright 2026 Xaere
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ZipquantumCronModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $ajax = true;
    public $ssl = true;

    public function displayAjax(): void
    {
        $expected = $this->zipQuantum()->store()->cronToken();
        $received = (string) Tools::getValue('token');
        if ($received === '' || !hash_equals($expected, $received)) {
            $this->respond(['success' => false, 'error' => 'invalid_token'], 403);
        }
        try {
            $summary = $this->zipQuantum()->queue()->process(max(1, min(50, (int) Tools::getValue('limit', 20))));
            $this->respond(['success' => true, 'summary' => $summary]);
        } catch (Throwable $error) {
            PrestaShopLogger::addLog('ZipQuantum cron: ' . $error->getMessage(), 3, null, 'Zipquantum', null, true);
            $this->respond(['success' => false, 'error' => 'processing_failed'], 500);
        }
    }

    private function zipQuantum(): Zipquantum
    {
        if (!$this->module instanceof Zipquantum) {
            throw new LogicException('ZipQuantum module instance is unavailable.');
        }

        return $this->module;
    }

    /** @param array<string, mixed> $payload */
    private function respond(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $this->ajaxRender(json_encode($payload, JSON_THROW_ON_ERROR));
        exit;
    }
}
