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

require_once __DIR__ . '/autoload.php';

use ZipQuantum\PrestaShop\Api\ApiClient;
use ZipQuantum\PrestaShop\Domain\ObjectPayloadFactory;
use ZipQuantum\PrestaShop\Repository\AssociationRepository;
use ZipQuantum\PrestaShop\Repository\QueueRepository;
use ZipQuantum\PrestaShop\Service\ModuleLifecycle;
use ZipQuantum\PrestaShop\Service\OAuthService;
use ZipQuantum\PrestaShop\Service\QueueService;
use ZipQuantum\PrestaShop\Service\SyncService;
use ZipQuantum\PrestaShop\Storage\ConfigurationStore;
use ZipQuantum\PrestaShop\Support\LocalPath;

final class Zipquantum extends Module
{
    private const HOOKS = [
        'actionObjectProductAddAfter',
        'actionObjectProductUpdateAfter',
        'actionObjectProductDeleteAfter',
        'actionObjectCategoryAddAfter',
        'actionObjectCategoryUpdateAfter',
        'actionObjectCategoryDeleteAfter',
        'actionObjectCartRuleAddAfter',
        'actionObjectCartRuleUpdateAfter',
        'actionObjectCartRuleDeleteAfter',
        'displayBackOfficeHeader',
    ];

    private ?ConfigurationStore $zqStore = null;
    private ?AssociationRepository $zqAssociations = null;
    private ?QueueRepository $zqQueueRepository = null;
    private ?ObjectPayloadFactory $zqPayloads = null;
    private ?ApiClient $zqApi = null;
    private ?SyncService $zqSync = null;
    private ?QueueService $zqQueue = null;

    public function __construct()
    {
        $this->name = 'zipquantum';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.0';
        $this->author = 'Xaere';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.1.0', 'max' => '9.99.99'];

        parent::__construct();

        $this->displayName = $this->trans('Smart Links & QR Codes: ZipQuantum', [], 'Modules.Zipquantum.Admin');
        $this->description = $this->trans(
            'Create and attach Smart Links and QR codes for products, categories and promotions.',
            [],
            'Modules.Zipquantum.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Uninstall the local connector? Remote Smart Links will be kept.',
            [],
            'Modules.Zipquantum.Admin'
        );
    }

    public function install(): bool
    {
        if (version_compare(PHP_VERSION, '8.1.0', '<') || !extension_loaded('curl') || !extension_loaded('openssl')) {
            $this->_errors[] = $this->trans('ZipQuantum requires PHP 8.1+, cURL and OpenSSL.', [], 'Modules.Zipquantum.Admin');
            return false;
        }
        if (!parent::install()) {
            return false;
        }
        if (!(new ModuleLifecycle())->installDatabase()) {
            parent::uninstall();
            return false;
        }
        foreach (self::HOOKS as $hook) {
            if (!$this->registerHook($hook)) {
                (new ModuleLifecycle())->uninstallDatabase();
                parent::uninstall();
                return false;
            }
        }
        if (!$this->installAjaxTab()) {
            (new ModuleLifecycle())->uninstallDatabase();
            parent::uninstall();
            return false;
        }
        $store = $this->store();
        $store->installationUuid();
        $store->cronToken();

        return true;
    }

    public function uninstall(): bool
    {
        // Remote Smart Links are deliberately never deleted here.
        $this->removeAjaxTab();
        $this->deleteConfiguration();

        return (new ModuleLifecycle())->uninstallDatabase() && parent::uninstall();
    }

    public function getContent(): string
    {
        $output = '';
        if (Tools::isSubmit('submitZipquantumSettings')) {
            $types = array_values(array_intersect(
                ObjectPayloadFactory::OBJECT_TYPES,
                array_map('strval', (array) Tools::getValue('ZQPS_OBJECT_TYPES', []))
            ));
            $subdomain = strtolower(trim((string) Tools::getValue('ZQPS_MANAGED_SUBDOMAIN', '')));
            $subdomain = preg_replace('/[^a-z0-9-]/', '', $subdomain) ?: '';
            $customDomain = strtolower(trim((string) Tools::getValue('ZQPS_CUSTOM_DOMAIN', '')));
            $validDomain = preg_match(
                '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
                $customDomain
            );
            if ($customDomain !== '' && !$validDomain) {
                $this->_errors[] = $this->trans('Enter a valid verified custom domain.', [], 'Modules.Zipquantum.Admin');
            } else {
                $this->store()->set(ConfigurationStore::MANAGED_SUBDOMAIN, $subdomain);
                $this->store()->set(ConfigurationStore::CUSTOM_DOMAIN, $customDomain);
                $this->store()->set(ConfigurationStore::AUTO_CREATE, (bool) Tools::getValue('ZQPS_AUTO_CREATE', false));
                $this->store()->setJson(ConfigurationStore::OBJECT_TYPES, $types);
                $this->store()->set(
                    ConfigurationStore::PROMOTION_DESTINATION,
                    LocalPath::normalize((string) Tools::getValue('ZQPS_PROMOTION_DEST', '/order'))
                );
                $output .= $this->displayConfirmation($this->trans('Settings saved.', [], 'Admin.Notifications.Success'));
            }
        }
        foreach ($this->_errors as $error) {
            $output .= $this->displayError($error);
        }

        $settings = $this->store()->settings();
        $context = $this->store()->getJson(ConfigurationStore::CONTEXT, []);
        $credentials = $this->store()->getSecret(ConfigurationStore::CREDENTIALS, []);
        $state = $this->store()->getJson(ConfigurationStore::STATE, []);
        $ajaxUrl = $this->context->link->getAdminLink('AdminZipquantumAjax');
        $cronUrl = $this->context->link->getModuleLink(
            $this->name,
            'cron',
            ['token' => $this->store()->cronToken()],
            true,
            (int) $this->context->language->id,
            $this->store()->shopId()
        );
        $this->context->smarty->assign([
            'zq_action_url' => $this->getConfigurationPageUrl(),
            'zq_ajax_url' => $ajaxUrl,
            'zq_cron_url' => $cronUrl,
            'zq_settings' => $settings,
            'zq_connected' => is_array($credentials) && !empty($credentials['access_token']),
            'zq_context' => is_array($context) ? $context : [],
            'zq_state' => is_array($state) ? $state : [],
            'zq_queue_stats' => $this->queueRepository()->stats($this->store()->shopId()),
            'zq_queue_statuses' => ['pending', 'processing', 'retry', 'blocked', 'quarantined', 'failed', 'complete'],
            'zq_associations' => $this->associations()->recent($this->store()->shopId()),
            'zq_logo_url' => $this->_path . 'logo.png',
        ]);

        return $output . $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    public function hookDisplayBackOfficeHeader(): void
    {
        if (
            (string) Tools::getValue('configure') !== $this->name
            && (string) Tools::getValue('controller') !== 'AdminZipquantumAjax'
        ) {
            return;
        }
        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        $this->context->controller->addJS($this->_path . 'views/js/admin.js?v=1002');
    }

    public function hookActionObjectProductAddAfter(array $params): void
    {
        $this->enqueueFromHook('product', $params);
    }

    public function hookActionObjectProductUpdateAfter(array $params): void
    {
        $this->enqueueFromHook('product', $params);
    }

    public function hookActionObjectProductDeleteAfter(array $params): void
    {
        $this->deleteFromHook('product', $params);
    }

    public function hookActionObjectCategoryAddAfter(array $params): void
    {
        $this->enqueueFromHook('category', $params);
    }

    public function hookActionObjectCategoryUpdateAfter(array $params): void
    {
        $this->enqueueFromHook('category', $params);
    }

    public function hookActionObjectCategoryDeleteAfter(array $params): void
    {
        $this->deleteFromHook('category', $params);
    }

    public function hookActionObjectCartRuleAddAfter(array $params): void
    {
        $this->enqueueFromHook('promotion', $params);
    }

    public function hookActionObjectCartRuleUpdateAfter(array $params): void
    {
        $this->enqueueFromHook('promotion', $params);
    }

    public function hookActionObjectCartRuleDeleteAfter(array $params): void
    {
        $this->deleteFromHook('promotion', $params);
    }

    public function store(): ConfigurationStore
    {
        return $this->zqStore ??= new ConfigurationStore(
            (int) $this->context->shop->id,
            (int) $this->context->shop->id_shop_group
        );
    }

    public function associations(): AssociationRepository
    {
        return $this->zqAssociations ??= new AssociationRepository();
    }

    public function queueRepository(): QueueRepository
    {
        return $this->zqQueueRepository ??= new QueueRepository();
    }

    public function payloads(): ObjectPayloadFactory
    {
        return $this->zqPayloads ??= new ObjectPayloadFactory(
            $this,
            $this->store(),
            $this->associations(),
            $this->context->link,
            (int) $this->context->language->id
        );
    }

    public function api(): ApiClient
    {
        return $this->zqApi ??= new ApiClient(
            $this->store(),
            $this->version,
            ApiClient::DEFAULT_BASE_URI,
            (string) $this->context->link->getBaseLink($this->store()->shopId(), true)
        );
    }

    public function oauth(): OAuthService
    {
        return new OAuthService(
            $this->api(),
            $this->store(),
            (string) $this->context->link->getBaseLink($this->store()->shopId(), true)
        );
    }

    public function sync(): SyncService
    {
        return $this->zqSync ??= new SyncService($this->api(), $this->store(), $this->associations(), $this->payloads());
    }

    public function queue(): QueueService
    {
        return $this->zqQueue ??= new QueueService($this->queueRepository(), $this->sync(), $this->store());
    }

    public function translateForShop(string $message): string
    {
        return $this->trans($message, [], 'Modules.Zipquantum.Shop');
    }

    private function enqueueFromHook(string $objectType, array $params): void
    {
        $object = $params['object'] ?? null;
        $objectId = is_object($object) ? (int) ($object->id ?? 0) : 0;
        if ($objectId < 1 || !$this->store()->getSecret(ConfigurationStore::CREDENTIALS, [])) {
            return;
        }
        $association = $this->associations()->find($this->store()->shopId(), $objectType, $objectId);
        if ($association !== []) {
            if (
                ($association['local_status'] ?? '') !== 'quarantined'
                && ($association['management_mode'] ?? 'managed') === 'managed'
            ) {
                $this->queue()->enqueue($objectType, $objectId, $this->payloads()->build($objectType, $objectId));
            }
            return;
        }
        $settings = $this->store()->settings();
        if (!empty($settings['auto_create']) && in_array($objectType, (array) $settings['object_types'], true)) {
            $this->queue()->enqueue($objectType, $objectId, $this->payloads()->build($objectType, $objectId));
        }
    }

    private function deleteFromHook(string $objectType, array $params): void
    {
        $object = $params['object'] ?? null;
        $objectId = is_object($object) ? (int) ($object->id ?? 0) : 0;
        if ($objectId < 1) {
            return;
        }
        // Local cleanup only: there is intentionally no remote DELETE request.
        $this->associations()->deleteLocal($this->store()->shopId(), $objectType, $objectId);
        $this->queueRepository()->cancelObject($this->store()->shopId(), $objectType, $objectId);
    }

    private function installAjaxTab(): bool
    {
        if ((int) Tab::getIdFromClassName('AdminZipquantumAjax') > 0) {
            return true;
        }
        $tab = new Tab();
        $tab->active = true;
        $tab->class_name = 'AdminZipquantumAjax';
        $tab->module = $this->name;
        $tab->id_parent = -1;
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = 'ZipQuantum AJAX';
        }

        return (bool) $tab->add();
    }

    private function removeAjaxTab(): void
    {
        $id = (int) Tab::getIdFromClassName('AdminZipquantumAjax');
        if ($id > 0) {
            (new Tab($id))->delete();
        }
    }

    private function deleteConfiguration(): void
    {
        foreach (
            [
                ConfigurationStore::MANAGED_SUBDOMAIN,
                ConfigurationStore::CUSTOM_DOMAIN,
                ConfigurationStore::AUTO_CREATE,
                ConfigurationStore::OBJECT_TYPES,
                ConfigurationStore::PROMOTION_DESTINATION,
                ConfigurationStore::INSTALLATION,
                ConfigurationStore::CREDENTIALS,
                ConfigurationStore::OAUTH_PENDING,
                ConfigurationStore::CONTEXT,
                ConfigurationStore::STATE,
                ConfigurationStore::CRON_TOKEN,
            ] as $key
        ) {
            Configuration::deleteByName($key);
        }
    }

    private function getConfigurationPageUrl(): string
    {
        return $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name]);
    }
}
