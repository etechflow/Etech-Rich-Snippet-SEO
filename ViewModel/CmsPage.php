<?php
declare(strict_types=1);

namespace Etechflow\RichSnippets\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\UrlInterface;

/**
 * WebPage schema node for CMS pages, cross-linked to the WebSite node.
 */
class CmsPage implements ArgumentInterface
{
    public function __construct(
        private StoreManagerInterface $storeManager,
        private PageConfig $pageConfig,
        private UrlInterface $urlBuilder
    ) {
    }

    public function getNode(): ?array
    {
        try {
            $store = $this->storeManager->getStore();
            $url   = $this->urlBuilder->getCurrentUrl();
            return array_filter([
                '@type'       => 'WebPage',
                '@id'         => $url . '#webpage',
                'url'         => $url,
                'name'        => (string)$this->pageConfig->getTitle()->get(),
                'description' => trim(strip_tags((string)$this->pageConfig->getDescription())),
                'isPartOf'    => ['@id' => $store->getBaseUrl() . '#website'],
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
