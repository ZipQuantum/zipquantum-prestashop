<?php
/**
 * ZipQuantum Smart Links and QR Codes integration for PrestaShop.
 *
 * @author Xaere
 * @copyright 2026 Xaere
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ZipQuantum\PrestaShop\Domain;

use ZipQuantum\PrestaShop\Repository\AssociationRepository;
use ZipQuantum\PrestaShop\Storage\ConfigurationStore;
use ZipQuantum\PrestaShop\Support\LocalPath;
use ZipQuantum\PrestaShop\Support\PublicPreviewUrl;

if (!defined('_PS_VERSION_') && !defined('ZQPS_TESTING')) {
    exit;
}

final class ObjectPayloadFactory
{
    public const OBJECT_TYPES = ['product', 'category', 'promotion'];

    private \Module $module;
    private ConfigurationStore $store;
    private AssociationRepository $associations;
    private \Link $link;
    private int $languageId;

    public function __construct(
        \Module $module,
        ConfigurationStore $store,
        AssociationRepository $associations,
        \Link $link,
        int $languageId,
    ) {
        $this->module = $module;
        $this->store = $store;
        $this->associations = $associations;
        $this->link = $link;
        $this->languageId = $languageId;
    }

    /** @return array<string, mixed> */
    public function build(string $objectType, int $objectId, ?string $managementMode = null, ?int $linkId = null): array
    {
        if (!in_array($objectType, self::OBJECT_TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported PrestaShop object type.');
        }
        $current = $this->associations->find($this->store->shopId(), $objectType, $objectId);
        $mode = $managementMode ?? (string) ($current['management_mode'] ?? 'managed');
        $payload = [
            'provider' => 'prestashop',
            'object_type' => $objectType,
            'object_id' => (string) $objectId,
            'management_mode' => $mode,
        ];
        if ($mode === 'attached') {
            $attachedId = $linkId ?? (int) ($current['link_id'] ?? 0);
            if ($attachedId < 1) {
                throw new \InvalidArgumentException('A Smart Link ID is required for attached mode.');
            }
            $payload['link_id'] = $attachedId;

            return $payload;
        }
        if ($mode !== 'managed') {
            throw new \InvalidArgumentException('Invalid Smart Link management mode.');
        }

        $content = $this->content($objectType, $objectId);
        $settings = $this->store->settings();
        $payload['managed_fields'] = [
            'destination_url',
            'preview_title',
            'preview_description',
            'preview_image_url',
        ];
        $payload['source_url'] = $content['url'];
        $link = [
            'link' => $content['url'],
            'reference' => $content['reference'],
            'preview_title' => $content['title'],
            'preview_description' => $content['description'],
        ];
        if ($content['image'] !== '') {
            $link['preview_image_url'] = $content['image'];
        }
        if ((string) $settings['custom_domain'] !== '') {
            $link['custom_domain'] = (string) $settings['custom_domain'];
        } elseif ((string) $settings['managed_subdomain'] !== '') {
            $link['subdomain'] = (string) $settings['managed_subdomain'];
        }
        $payload['link'] = $link;

        return $payload;
    }

    /** @return array{url:string,title:string,description:string,image:string,reference:string} */
    private function content(string $objectType, int $objectId): array
    {
        $languageId = $this->languageId;
        $shopId = $this->store->shopId();

        if ($objectType === 'product') {
            $product = new \Product($objectId, false, $languageId, $shopId);
            if (!\Validate::isLoadedObject($product)) {
                throw new \InvalidArgumentException('PrestaShop product not found.');
            }
            $rewrite = is_string($product->link_rewrite) ? $product->link_rewrite : (string) ($product->link_rewrite[$languageId] ?? '');
            $cover = \Image::getCover($objectId);
            $image = '';
            if (is_array($cover) && !empty($cover['id_image'])) {
                $image = (string) $this->link->getImageLink($rewrite, (int) $cover['id_image'], 'large_default');
                if ($image !== '' && !preg_match('#^https?://#i', $image)) {
                    $image = 'https://' . ltrim($image, '/');
                }
                $image = PublicPreviewUrl::sanitize($image);
            }

            return [
                'url' => (string) $this->link->getProductLink($product, null, null, null, $languageId, $shopId),
                'title' => $this->text($product->name, $languageId, 255),
                'description' => $this->text($product->description_short ?: $product->description, $languageId, 255),
                'image' => $image,
                'reference' => $this->reference($rewrite ?: (string) $product->name, $objectId),
            ];
        }

        if ($objectType === 'category') {
            $category = new \Category($objectId, $languageId, $shopId);
            if (!\Validate::isLoadedObject($category)) {
                throw new \InvalidArgumentException('PrestaShop category not found.');
            }
            $rewrite = is_string($category->link_rewrite)
                ? $category->link_rewrite
                : (string) ($category->link_rewrite[$languageId] ?? '');
            $image = '';
            $categoryImage = _PS_CAT_IMG_DIR_ . $objectId . '.jpg';
            if (is_file($categoryImage)) {
                $image = (string) $this->link->getCatImageLink($rewrite, $objectId, 'category_default');
                if ($image !== '' && !preg_match('#^https?://#i', $image)) {
                    $image = 'https://' . ltrim($image, '/');
                }
                $image = PublicPreviewUrl::sanitize($image);
            }

            return [
                'url' => (string) $this->link->getCategoryLink($category, null, $languageId, null, $shopId),
                'title' => $this->text($category->name, $languageId, 255),
                'description' => $this->text($category->description, $languageId, 255),
                'image' => $image,
                'reference' => $this->reference($rewrite ?: (string) $category->name, $objectId),
            ];
        }

        $promotion = new \CartRule($objectId, $languageId);
        if (!\Validate::isLoadedObject($promotion)) {
            throw new \InvalidArgumentException('PrestaShop promotion not found.');
        }
        $settings = $this->store->settings();
        $destination = LocalPath::normalize((string) $settings['promotion_destination']);
        if ((string) $promotion->code !== '') {
            $url = (string) $this->link->getModuleLink(
                $this->module->name,
                'coupon',
                ['code' => (string) $promotion->code, 'to' => $destination],
                true,
                $languageId,
                $shopId
            );
        } else {
            $url = rtrim((string) $this->link->getBaseLink($shopId, true), '/') . $destination;
        }
        $description = $this->promotionDescription($promotion);

        return [
            'url' => $url,
            'title' => $this->text($promotion->name, $languageId, 255),
            'description' => $description,
            'image' => '',
            'reference' => $this->reference((string) ($promotion->code ?: $this->text($promotion->name, $languageId, 80)), $objectId),
        ];
    }

    /** @param mixed $value */
    private function text($value, int $languageId, int $limit): string
    {
        if (is_array($value)) {
            $value = $value[$languageId] ?? reset($value) ?: '';
        }
        $text = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text) ?: '';

        return function_exists('mb_substr') ? mb_substr($text, 0, $limit) : substr($text, 0, $limit);
    }

    private function reference(string $value, int $objectId): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
        $value = trim(substr($value, 0, 60), '-');

        return trim(($value !== '' ? $value : 'item') . '-' . $objectId, '-');
    }

    private function promotionDescription(\CartRule $rule): string
    {
        $parts = [];
        if ((float) $rule->reduction_percent > 0) {
            $parts[] = rtrim(rtrim((string) $rule->reduction_percent, '0'), '.') . '% discount';
        }
        if ((float) $rule->reduction_amount > 0) {
            $parts[] = (string) $rule->reduction_amount . ' discount';
        }
        if ((bool) $rule->free_shipping) {
            $parts[] = 'Free shipping';
        }

        return implode(' - ', $parts) ?: 'Store promotion';
    }
}
