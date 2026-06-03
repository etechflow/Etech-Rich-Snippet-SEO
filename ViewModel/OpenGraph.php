<?php
declare(strict_types=1);

namespace Etechflow\RichSnippets\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\UrlInterface;
use Magento\Catalog\Pricing\Price\FinalPrice;

/**
 * OpenGraph + Twitter Card meta for product / category / generic pages.
 * Derives title/description from the entity directly -- deliberately does NOT
 * inject Page\Config, which would be a circular dependency when this renders
 * inside the <head> (head.additional).
 */
class OpenGraph implements ArgumentInterface
{
    public function __construct(
        private Registry $registry,
        private StoreManagerInterface $storeManager,
        private ImageHelper $imageHelper,
        private ScopeConfigInterface $scopeConfig,
        private UrlInterface $urlBuilder
    ) {
    }

    public function getOg(): array
    {
        try {
            $store = $this->storeManager->getStore();
            $site  = (string)$store->getFrontendName();

            $product  = $this->registry->registry('current_product');
            $category = $this->registry->registry('current_category');

            if ($product && $product->getId()) {
                $img = $this->imageHelper->init($product, 'product_base_image')
                    ->keepAspectRatio(true)->resize(800)->getUrl();
                return array_filter([
                    'og:type'                => 'product',
                    'og:url'                 => $product->getProductUrl(),
                    'og:title'               => (string)($product->getMetaTitle() ?: $product->getName()),
                    'og:description'         => $this->clean((string)($product->getMetaDescription() ?: $product->getShortDescription())),
                    'og:image'               => $img,
                    'og:site_name'           => $site,
                    'product:price:amount'   => number_format($this->finalPrice($product), 2, '.', ''),
                    'product:price:currency' => $store->getCurrentCurrencyCode(),
                    'product:availability'   => $product->isAvailable() ? 'in stock' : 'out of stock',
                ]);
            }
            if ($category && $category->getId()) {
                return array_filter([
                    'og:type'        => 'website',
                    'og:url'         => $category->getUrl(),
                    'og:title'       => (string)($category->getData('meta_title') ?: $category->getName()),
                    'og:description' => $this->clean((string)($category->getData('meta_description') ?: $category->getData('description'))),
                    'og:image'       => $category->getImageUrl() ? (string)$category->getImageUrl() : '',
                    'og:site_name'   => $site,
                ]);
            }
            return array_filter([
                'og:type'        => 'website',
                'og:url'         => $this->urlBuilder->getCurrentUrl(),
                'og:title'       => $site,
                'og:description' => $this->clean((string)$this->scopeConfig->getValue('design/head/default_description', ScopeInterface::SCOPE_STORE)),
                'og:site_name'   => $site,
            ]);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getTwitter(): array
    {
        $og = $this->getOg();
        if (!$og) {
            return [];
        }
        $img = $og['og:image'] ?? '';
        return array_filter([
            'twitter:card'        => $img ? 'summary_large_image' : 'summary',
            'twitter:title'       => $og['og:title'] ?? '',
            'twitter:description' => $og['og:description'] ?? '',
            'twitter:image'       => $img,
        ]);
    }

    private function finalPrice($product): float
    {
        try {
            $a   = $product->getPriceInfo()->getPrice(FinalPrice::PRICE_CODE)->getAmount();
            $inc = in_array((int)$this->scopeConfig->getValue('tax/display/type', ScopeInterface::SCOPE_STORE), [2, 3], true);
            return $inc ? (float)$a->getValue() : (float)$a->getBaseAmount();
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    private function clean(string $h): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($h)));
    }
}
