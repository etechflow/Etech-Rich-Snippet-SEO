<?php
declare(strict_types=1);

namespace Etechflow\RichSnippets\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\UrlInterface;

/**
 * Global schema.org nodes shared across every page: Organization + WebSite
 * (with SearchAction). Cross-linked by @id so Product.offers.seller and
 * WebSite.publisher resolve to the single Organization node.
 */
class Site implements ArgumentInterface
{
    private const ORG = 'https://schema.org';

    public function __construct(
        private StoreManagerInterface $storeManager,
        private ScopeConfigInterface $scopeConfig
    ) {
    }

    public function organizationId(): string
    {
        return $this->baseUrl() . '#organization';
    }

    public function getOrganizationNode(): array
    {
        $store = $this->storeManager->getStore();
        $node = [
            '@type' => 'Organization',
            '@id'   => $this->organizationId(),
            'name'  => $store->getFrontendName(),
            'url'   => $this->baseUrl(),
        ];
        if ($logo = $this->logoUrl()) {
            $node['logo'] = $logo;
        }
        if ($phone = (string)$this->scopeConfig->getValue('general/store_information/phone', ScopeInterface::SCOPE_STORE)) {
            $node['telephone'] = $phone;
        }
        return array_filter($node);
    }

    public function getWebsiteNode(): array
    {
        $store = $this->storeManager->getStore();
        return [
            '@type'     => 'WebSite',
            '@id'       => $this->baseUrl() . '#website',
            'url'       => $this->baseUrl(),
            'name'      => $store->getFrontendName(),
            'publisher' => ['@id' => $this->organizationId()],
            'potentialAction' => [
                '@type'  => 'SearchAction',
                'target' => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $this->baseUrl() . 'catalogsearch/result/?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    private function logoUrl(): ?string
    {
        $logo = (string)$this->scopeConfig->getValue('design/header/logo_src', ScopeInterface::SCOPE_STORE);
        if (!$logo) {
            return null;
        }
        try {
            $media = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            return $media . 'logo/' . ltrim($logo, '/');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function baseUrl(): string
    {
        try {
            return $this->storeManager->getStore()->getBaseUrl();
        } catch (\Throwable $e) {
            return '';
        }
    }
}
