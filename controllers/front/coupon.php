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

use ZipQuantum\PrestaShop\Support\LocalPath;

final class ZipquantumCouponModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $ssl = true;

    public function initContent(): void
    {
        parent::initContent();
        $code = trim((string) Tools::getValue('code'));
        $cartRuleId = $code !== '' ? (int) CartRule::getIdByCode($code) : 0;
        $cartRule = new CartRule($cartRuleId);
        if ($cartRuleId < 1 || !Validate::isLoadedObject($cartRule) || !(bool) $cartRule->active) {
            header('HTTP/1.1 404 Not Found');
            exit($this->zipQuantum()->translateForShop('This promotion is unavailable.'));
        }
        if (!$this->context->cart || !(int) $this->context->cart->id) {
            $cart = new Cart();
            $cart->id_shop_group = (int) $this->context->shop->id_shop_group;
            $cart->id_shop = (int) $this->context->shop->id;
            $cart->id_lang = (int) $this->context->language->id;
            $cart->id_currency = (int) $this->context->currency->id;
            $cart->id_customer = (int) $this->context->customer->id;
            $cart->id_guest = (int) $this->context->cookie->id_guest;
            if (!$cart->add()) {
                header('HTTP/1.1 500 Internal Server Error');
                exit($this->zipQuantum()->translateForShop('The promotion could not be applied.'));
            }
            $this->context->cart = $cart;
            $this->context->cookie->__set('id_cart', (int) $cart->id);
        }
        $validationError = $cartRule->checkValidity($this->context);
        if ($validationError) {
            header('HTTP/1.1 422 Unprocessable Entity');
            exit((string) $validationError);
        }
        $result = $this->context->cart->addCartRule($cartRuleId);
        if ($result !== true) {
            header('HTTP/1.1 409 Conflict');
            exit($this->zipQuantum()->translateForShop('The promotion could not be applied.'));
        }
        $destination = LocalPath::normalize((string) Tools::getValue('to', '/order'));
        $url = rtrim((string) $this->context->link->getBaseLink((int) $this->context->shop->id, true), '/') . $destination;
        header('Referrer-Policy: no-referrer');
        Tools::redirect($url);
    }

    private function zipQuantum(): Zipquantum
    {
        if (!$this->module instanceof Zipquantum) {
            throw new LogicException('ZipQuantum module instance is unavailable.');
        }

        return $this->module;
    }
}
