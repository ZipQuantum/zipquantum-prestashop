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

use ZipQuantum\PrestaShop\Api\ApiException;
use ZipQuantum\PrestaShop\Domain\ObjectPayloadFactory;
use ZipQuantum\PrestaShop\Storage\ConfigurationStore;

final class AdminZipquantumAjaxController extends ModuleAdminController
{
    public $ajax = true;

    public function displayAjax(): void
    {
        if (!$this->context->employee || !(int) $this->context->employee->id) {
            $this->respondError('Permission denied.', 403);
        }
        $action = (string) Tools::getValue('action');
        $method = 'action' . ucfirst($action);
        if (!method_exists($this, $method)) {
            $this->respondError('Unknown ZipQuantum action.', 404);
        }

        try {
            $result = $this->{$method}();
            $this->ajaxRender(json_encode(['success' => true, 'data' => $result], JSON_THROW_ON_ERROR));
        } catch (ApiException $error) {
            if ($error->status() === 409 && $error->apiCode() === 'installation_identity_mismatch') {
                $this->zipQuantum()->store()->setJson(ConfigurationStore::STATE, [
                    'identity_mismatch' => true,
                    'detected_at' => gmdate('c'),
                ]);
            }
            $this->respondError($error->getMessage(), $error->status() ?: 500, $error->apiCode());
        } catch (Throwable $error) {
            PrestaShopLogger::addLog('ZipQuantum: ' . $error->getMessage(), 3, null, 'Zipquantum', null, true);
            $this->respondError($error->getMessage(), 500);
        }
    }

    /** @return array<string, mixed> */
    private function actionOauthStart(): array
    {
        return $this->zipQuantum()->oauth()->start((string) Tools::getValue('intent', 'connect'));
    }

    /** @return array<string, mixed> */
    private function actionOauthPoll(): array
    {
        return $this->zipQuantum()->oauth()->poll();
    }

    /** @return array<string, bool> */
    private function actionDisconnect(): array
    {
        $this->zipQuantum()->oauth()->disconnect();

        return ['disconnected' => true];
    }

    /** @return array<string, bool> */
    private function actionNewInstallation(): array
    {
        $module = $this->zipQuantum();
        $module->store()->createNewInstallation();
        $module->associations()->quarantineAll($module->store()->shopId());
        $module->queueRepository()->quarantineAll($module->store()->shopId());

        return ['created' => true];
    }

    /** @return array<string, mixed> */
    private function actionSync(): array
    {
        [$objectType, $objectId] = $this->objectInput();
        $module = $this->zipQuantum();
        $payload = $module->payloads()->build($objectType, $objectId, 'managed');
        $module->queue()->enqueue($objectType, $objectId, $payload);
        $summary = $module->queue()->process(1);

        return ['queued' => true, 'summary' => $summary];
    }

    /** @return array<string, mixed> */
    private function actionAttach(): array
    {
        [$objectType, $objectId] = $this->objectInput();
        $linkId = (int) Tools::getValue('link_id');
        if ($linkId < 1) {
            throw new InvalidArgumentException('A Smart Link ID is required.');
        }
        $module = $this->zipQuantum();
        $payload = $module->payloads()->build($objectType, $objectId, 'attached', $linkId);
        $module->queue()->enqueue($objectType, $objectId, $payload);
        $summary = $module->queue()->process(1);

        return ['queued' => true, 'summary' => $summary];
    }

    /** @return array<string, int> */
    private function actionBulkEnqueue(): array
    {
        $objectType = (string) Tools::getValue('object_type');
        if (!in_array($objectType, ObjectPayloadFactory::OBJECT_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported PrestaShop object type.');
        }
        $limit = max(1, min(500, (int) Tools::getValue('limit', 500)));
        $ids = $this->objectIds($objectType, $limit);
        $queued = 0;
        foreach ($ids as $objectId) {
            try {
                if (
                    $this->zipQuantum()->queue()->enqueue(
                        $objectType,
                        $objectId,
                        $this->zipQuantum()->payloads()->build($objectType, $objectId)
                    )
                ) {
                    ++$queued;
                }
            } catch (Throwable $error) {
                PrestaShopLogger::addLog(
                    'ZipQuantum bulk skip ' . $objectType . '#' . $objectId . ': ' . $error->getMessage(),
                    2,
                    null,
                    'Zipquantum',
                    null,
                    true
                );
            }
        }

        return ['queued' => $queued];
    }

    /** @return array<string, int> */
    private function actionProcessQueue(): array
    {
        return $this->zipQuantum()->queue()->process(max(1, min(50, (int) Tools::getValue('limit', 10))));
    }

    /** @return array<string, bool> */
    private function actionRetry(): array
    {
        return ['updated' => $this->zipQuantum()->queue()->retryFailed()];
    }

    /** @return array<string, bool> */
    private function actionResume(): array
    {
        return ['updated' => $this->zipQuantum()->queue()->resumeBlocked()];
    }

    /** @return array<string, int> */
    private function actionRefreshAnalytics(): array
    {
        return ['updated' => $this->zipQuantum()->sync()->refreshAnalytics()];
    }

    /** @return array{0:string,1:int} */
    private function objectInput(): array
    {
        $type = (string) Tools::getValue('object_type');
        $id = (int) Tools::getValue('object_id');
        if (!in_array($type, ObjectPayloadFactory::OBJECT_TYPES, true) || $id < 1) {
            throw new InvalidArgumentException('A valid object type and ID are required.');
        }

        return [$type, $id];
    }

    /** @return array<int, int> */
    private function objectIds(string $objectType, int $limit): array
    {
        $shopId = (int) $this->zipQuantum()->store()->shopId();
        if ($objectType === 'product') {
            $sql = 'SELECT DISTINCT p.`id_product` FROM `' . _DB_PREFIX_ . 'product` p'
                . ' INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.`id_product` = p.`id_product`'
                . ' WHERE ps.`id_shop` = ' . $shopId . ' AND ps.`active` = 1'
                . ' ORDER BY p.`id_product` ASC LIMIT ' . (int) $limit;
        } elseif ($objectType === 'category') {
            $sql = 'SELECT DISTINCT c.`id_category` FROM `' . _DB_PREFIX_ . 'category` c'
                . ' INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON cs.`id_category` = c.`id_category`'
                . ' WHERE cs.`id_shop` = ' . $shopId . ' AND c.`active` = 1'
                . ' ORDER BY c.`id_category` ASC LIMIT ' . (int) $limit;
        } else {
            $sql = 'SELECT DISTINCT cr.`id_cart_rule` FROM `' . _DB_PREFIX_ . 'cart_rule` cr'
                . ' INNER JOIN `' . _DB_PREFIX_ . 'cart_rule_shop` crs ON crs.`id_cart_rule` = cr.`id_cart_rule`'
                . ' WHERE crs.`id_shop` = ' . $shopId . ' AND cr.`active` = 1'
                . ' ORDER BY cr.`id_cart_rule` ASC LIMIT ' . (int) $limit;
        }
        $values = Db::getInstance()->executeS($sql);
        $key = $objectType === 'promotion' ? 'id_cart_rule' : 'id_' . $objectType;

        return array_map(static fn (array $row): int => (int) $row[$key], is_array($values) ? $values : []);
    }

    private function zipQuantum(): Zipquantum
    {
        if (!$this->module instanceof Zipquantum) {
            throw new LogicException('ZipQuantum module instance is unavailable.');
        }

        return $this->module;
    }

    private function respondError(string $message, int $status, string $code = 'request_failed'): void
    {
        http_response_code($status);
        $this->ajaxRender(json_encode([
            'success' => false,
            'error' => ['message' => $message, 'code' => $code],
        ], JSON_THROW_ON_ERROR));
        exit;
    }
}
