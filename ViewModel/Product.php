<?php
declare(strict_types=1);

namespace Etechflow\RichSnippets\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Review\Model\Rating;
use Magento\Review\Model\Review;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Shipping\Model\Config as ShippingConfig;

/**
 * schema.org Product / ProductGroup node for the current product page.
 * Hyva-native pure ViewModel.
 */
class Product implements ArgumentInterface
{
    private const ORG = 'https://schema.org';
    private const GR  = 'http://purl.org/goodrelations/v1#';

    /** schema.org property => default attribute-code hints (overridable via config) */
    private const VARY_PROPS = ['color', 'size', 'material', 'pattern'];

    public function __construct(
        private Registry $registry,
        private StoreManagerInterface $storeManager,
        private ImageHelper $imageHelper,
        private ScopeConfigInterface $scopeConfig,
        private ReviewCollectionFactory $reviewCollectionFactory,
        private Rating $rating,
        private PaymentConfig $paymentConfig,
        private ShippingConfig $shippingConfig
    ) {
    }

    public function getProduct()
    {
        $p = $this->registry->registry('current_product');
        return $p && $p->getId() ? $p : null;
    }

    public function getNode(): ?array
    {
        $product = $this->getProduct();
        if (!$product) {
            return null;
        }
        try {
            $store    = $this->storeManager->getStore();
            $currency = $store->getCurrentCurrencyCode();
            $isConfig = $product->getTypeId() === Configurable::TYPE_CODE;

            $variants = ($isConfig && $this->cfgFlag('variants_enabled', true))
                ? $this->variants($product, $store, $currency)
                : [];
            $isGroup = $isConfig && !empty($variants);

            $node = [
                '@type' => $isGroup ? 'ProductGroup' : 'Product',
                '@id'   => $product->getProductUrl() . '#product',
                'name'  => (string)$product->getName(),
                'sku'   => (string)$product->getSku(),
                'url'   => $product->getProductUrl(),
            ];
            if ($mpn = $this->attr($product, (string)($this->cfg('mpn_attribute') ?: 'sku'))) {
                $node['mpn'] = $mpn;
            }
            if ($image = $this->imageHelper->init($product, 'product_page_image_large')->getUrl()) {
                $node['image'] = $image;
            }
            if ($desc = $this->cleanDesc((string)($product->getShortDescription() ?: $product->getDescription()))) {
                $node['description'] = $desc;
            }
            if ($cat = $this->categoryName($product)) {
                $node['category'] = $cat;
            }
            if ($brand = $this->attr($product, (string)($this->cfg('brand_attribute') ?: 'brand'))) {
                $node['brand'] = ['@type' => 'Brand', 'name' => $brand];
            }
            foreach (['model', 'color', 'size', 'material', 'pattern'] as $prop) {
                $code = (string)$this->cfg($prop . '_attribute');
                if ($code && ($v = $this->attr($product, $code))) {
                    $node[$prop] = $v;
                }
            }
            if ($weight = $this->weight($product)) {
                $node['weight'] = $weight;
            }
            foreach (['gtin13', 'gtin12', 'gtin8', 'gtin14'] as $g) {
                $code = (string)$this->cfg($g . '_attribute');
                if ($code && ($v = $this->attr($product, $code))) {
                    $node[$g] = $v;
                }
            }

            $node['offers'] = $isConfig
                ? $this->aggregateOffer($product, $store, $currency)
                : $this->singleOffer($product, $store, $currency);

            [$agg, $reviews] = $this->reviews($product, $store);
            if ($agg) {
                $node['aggregateRating'] = $agg;
            }
            if ($reviews) {
                $node['review'] = $reviews;
            }

            if ($isGroup) {
                $node['productGroupID'] = (string)$product->getSku();
                $node['variesBy']       = $this->variesBy($product);
                $node['hasVariant']     = $variants;
            }

            return array_filter($node);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array configurable attribute code => frontend label */
    private function configAttributes($product): array
    {
        try {
            $out = [];
            foreach ($product->getTypeInstance()->getConfigurableAttributesAsArray($product) as $opt) {
                if (!empty($opt['attribute_code'])) {
                    $out[$opt['attribute_code']] = $opt['label'] ?? $opt['frontend_label'] ?? $opt['attribute_code'];
                }
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function variesBy($product): array
    {
        $out = [];
        foreach (array_keys($this->configAttributes($product)) as $code) {
            $out[] = in_array($code, self::VARY_PROPS, true) ? self::ORG . '/' . $code : $code;
        }
        return $out;
    }

    private function variants($product, $store, string $currency): array
    {
        $attrs = $this->configAttributes($product);
        $variants = [];
        foreach ($product->getTypeInstance()->getUsedProducts($product) as $child) {
            $v = [
                '@type'                 => 'Product',
                'sku'                   => (string)$child->getSku(),
                'name'                  => (string)$child->getName(),
                'url'                   => $child->getProductUrl(),
                'inProductGroupWithID'  => (string)$product->getSku(),
            ];
            if ($img = $this->imageHelper->init($child, 'product_page_image_large')->getUrl()) {
                $v['image'] = $img;
            }
            foreach ($attrs as $code => $label) {
                $val = $this->attr($child, $code);
                if (!$val) {
                    continue;
                }
                if (in_array($code, self::VARY_PROPS, true)) {
                    $v[$code] = $val;
                } else {
                    $v['additionalProperty'][] = ['@type' => 'PropertyValue', 'name' => $label, 'value' => $val];
                }
            }
            $v['offers'] = $this->singleOffer($child, $store, $currency);
            $variants[] = array_filter($v);
        }
        return $variants;
    }

    private function singleOffer($product, $store, string $currency): array
    {
        $final   = $this->priceVal($product, FinalPrice::PRICE_CODE);
        $regular = $this->priceVal($product, RegularPrice::PRICE_CODE);

        $offer = [
            '@type'           => 'Offer',
            'url'             => $product->getProductUrl(),
            'price'           => number_format($final, 2, '.', ''),
            'priceCurrency'   => $currency,
            'availability'    => self::ORG . '/' . ($product->isAvailable() ? 'InStock' : 'OutOfStock'),
            'itemCondition'   => $this->itemCondition($product),
            'priceValidUntil' => $this->priceValidUntil($product),
            'sku'             => (string)$product->getSku(),
            'seller'          => ['@id' => $store->getBaseUrl() . '#organization'],
        ];
        if ($regular > $final) {
            $offer['priceSpecification'] = [
                ['@type' => 'UnitPriceSpecification', 'priceType' => self::ORG . '/ListPrice',
                 'price' => number_format($regular, 2, '.', ''), 'priceCurrency' => $currency],
                ['@type' => 'UnitPriceSpecification', 'priceType' => self::ORG . '/SalePrice',
                 'price' => number_format($final, 2, '.', ''), 'priceCurrency' => $currency],
            ];
        }
        if ($pm = $this->paymentMethods()) {
            $offer['acceptedPaymentMethod'] = $pm;
        }
        if ($dm = $this->deliveryMethods()) {
            $offer['availableDeliveryMethod'] = $dm;
        }
        return array_filter($offer);
    }

    private function aggregateOffer($product, $store, string $currency): array
    {
        $children = $product->getTypeInstance()->getUsedProducts($product);
        $offers = [];
        $min = 0.0;
        $max = 0.0;
        foreach ($children as $child) {
            $price = $this->priceVal($child, FinalPrice::PRICE_CODE);
            if ($price <= 0) {
                continue;
            }
            $min = $min === 0.0 ? $price : min($min, $price);
            $max = max($max, $price);
            $offers[] = [
                '@type'         => 'Offer',
                'sku'           => (string)$child->getSku(),
                'price'         => number_format($price, 2, '.', ''),
                'priceCurrency' => $currency,
                'availability'  => self::ORG . '/' . ($child->isSalable() ? 'InStock' : 'OutOfStock'),
            ];
        }
        if (!$offers) {
            return ['@type' => 'Offer', 'priceCurrency' => $currency, 'availability' => self::ORG . '/OutOfStock'];
        }
        return [
            '@type'         => 'AggregateOffer',
            'priceCurrency' => $currency,
            'lowPrice'      => number_format($min, 2, '.', ''),
            'highPrice'     => number_format($max, 2, '.', ''),
            'offerCount'    => count($offers),
            'offers'        => $offers,
        ];
    }

    private function itemCondition($product): string
    {
        $attr = (string)$this->cfg('condition_attribute');
        if ($attr && ($raw = $this->attr($product, $attr))) {
            $v = strtolower($raw);
            if (str_contains($v, 'refurb')) {
                return self::ORG . '/RefurbishedCondition';
            }
            if (str_contains($v, 'used') || str_contains($v, 'second')) {
                return self::ORG . '/UsedCondition';
            }
            if (str_contains($v, 'damaged')) {
                return self::ORG . '/DamagedCondition';
            }
        }
        return self::ORG . '/NewCondition';
    }

    private function reviews($product, $store): array
    {
        $col = $this->reviewCollectionFactory->create()
            ->addStatusFilter(Review::STATUS_APPROVED)
            ->addEntityFilter('product', $product->getId())
            ->addStoreFilter($store->getId())
            ->setDateOrder();
        $reviews = [];
        $sum = 0.0;
        $cnt = 0;
        foreach ($col as $rev) {
            $s = $this->rating->getReviewSummary($rev->getId());
            $rv = null;
            if ($s->getSum() && $s->getCount()) {
                $rv = $s->getSum() / $s->getCount() / 20;
                $sum += $rv;
                $cnt++;
            }
            $r = array_filter([
                '@type'         => 'Review',
                'name'          => $rev->getData('title'),
                'datePublished' => $rev->getCreatedAt(),
                'reviewBody'    => strip_tags((string)$rev->getData('detail')),
                'author'        => ['@type' => 'Person', 'name' => $rev->getData('nickname') ?: 'User'],
            ]);
            if ($rv) {
                $r['reviewRating'] = ['@type' => 'Rating', 'ratingValue' => number_format($rv, 1),
                                      'bestRating' => 5, 'worstRating' => 1];
            }
            $reviews[] = $r;
        }
        $agg = null;
        if ($cnt > 0) {
            $agg = ['@type' => 'AggregateRating', 'ratingValue' => number_format($sum / $cnt, 1),
                    'reviewCount' => $col->getSize(), 'ratingCount' => $cnt, 'bestRating' => 5, 'worstRating' => 1];
        }
        return [$agg, $reviews];
    }

    private function categoryName($product): ?string
    {
        try {
            $col = $product->getCategoryCollection()->addAttributeToSelect('name')
                ->addAttributeToSort('level', 'desc')->setPageSize(1);
            $cat = $col->getFirstItem();
            return ($cat && $cat->getId()) ? (string)$cat->getName() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function weight($product): ?array
    {
        $w = (float)$product->getWeight();
        if ($w <= 0) {
            return null;
        }
        $unit = (string)($this->cfg('weight_unit') ?: 'KGM');
        return ['@type' => 'QuantitativeValue', 'value' => number_format($w, 4, '.', ''), 'unitCode' => $unit];
    }

    private function paymentMethods(): array
    {
        $map = ['paypal' => self::GR . 'PayPal', 'googlecheckout' => self::GR . 'GoogleCheckout', 'cash' => self::GR . 'Cash'];
        $out = [];
        foreach (array_keys($this->paymentConfig->getActiveMethods()) as $code) {
            foreach ($map as $needle => $uri) {
                if (strpos($code, $needle) !== false) {
                    $out[] = $uri;
                }
            }
        }
        return array_values(array_unique($out));
    }

    private function deliveryMethods(): array
    {
        $map = ['flatrate' => 'DeliveryModeFreight', 'freeshipping' => 'DeliveryModeFreight', 'tablerate' => 'DeliveryModeFreight',
                'dhl' => 'DHL', 'fedex' => 'FederalExpress', 'ups' => 'UPS', 'usps' => 'DeliveryModeMail'];
        $out = [];
        foreach (array_keys($this->shippingConfig->getActiveCarriers()) as $code) {
            if (isset($map[$code])) {
                $out[] = self::GR . $map[$code];
            }
        }
        return array_values(array_unique($out));
    }

    private function priceValidUntil($product): string
    {
        $d = $product->getData('special_to_date');
        if ($d && strtotime((string)$d) > time()) {
            return date('Y-m-d', strtotime((string)$d));
        }
        return date('Y-m-d', strtotime('+1 year'));
    }

    private function priceVal($product, string $code): float
    {
        $amount = $product->getPriceInfo()->getPrice($code)->getAmount();
        return $this->isIncludingTax() ? (float)$amount->getValue() : (float)$amount->getBaseAmount();
    }

    private function isIncludingTax(): bool
    {
        return in_array((int)$this->scopeConfig->getValue('tax/display/type', ScopeInterface::SCOPE_STORE), [2, 3], true);
    }

    private function cfg(string $key)
    {
        return $this->scopeConfig->getValue('etechflow_richsnippets/product/' . $key, ScopeInterface::SCOPE_STORE);
    }

    private function cfgFlag(string $key, bool $default): bool
    {
        $v = $this->scopeConfig->getValue('etechflow_richsnippets/product/' . $key, ScopeInterface::SCOPE_STORE);
        return $v === null ? $default : (bool)$v;
    }

    private function attr($product, string $code): ?string
    {
        if (!$code) {
            return null;
        }
        try {
            $text = $product->getAttributeText($code);
            if ($text) {
                return is_array($text) ? implode(', ', $text) : (string)$text;
            }
            $val = $product->getData($code);
            return ($val !== null && $val !== '') ? (string)$val : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function cleanDesc(string $html): ?string
    {
        $t = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
        return $t !== '' ? mb_substr($t, 0, 5000) : null;
    }
}
