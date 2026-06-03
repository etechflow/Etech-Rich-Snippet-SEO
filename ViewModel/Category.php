<?php
declare(strict_types=1);

namespace Etechflow\RichSnippets\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\Registry;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;

/**
 * Current-page ItemList for a category (Google-compliant: reflects only the
 * products visible on the viewed page). This is the snippet Mirasvit drops
 * on Hyva landing pages.
 */
class Category implements ArgumentInterface
{
    public function __construct(
        private Registry $registry,
        private RequestInterface $request,
        private ScopeConfigInterface $scopeConfig,
        private StoreManagerInterface $storeManager,
        private CollectionFactory $collectionFactory,
        private Visibility $visibility
    ) {
    }

    public function getCategory()
    {
        $c = $this->registry->registry('current_category');
        return $c && $c->getId() ? $c : null;
    }

    public function getItemListNode(): ?array
    {
        $category = $this->getCategory();
        if (!$category) {
            return null;
        }
        try {
            $store = $this->storeManager->getStore();
            if ((int)$category->getId() === (int)$store->getRootCategoryId()) {
                return null;
            }
            $pageSize = (int)($this->request->getParam('product_list_limit')
                ?: $this->scopeConfig->getValue('catalog/frontend/grid_per_page', ScopeInterface::SCOPE_STORE)
                ?: 12);
            $curPage = max(1, (int)$this->request->getParam('p', 1));

            $collection = $this->collectionFactory->create();
            $collection->addCategoryFilter($category)
                ->setVisibility($this->visibility->getVisibleInCatalogIds())
                ->addAttributeToFilter('status', Status::STATUS_ENABLED)
                ->addAttributeToSelect('name')
                ->setPageSize($pageSize)
                ->setCurPage($curPage);

            $items = [];
            $pos = ($curPage - 1) * $pageSize + 1;
            foreach ($collection as $product) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos++,
                    'url'      => $product->getProductUrl(),
                    'name'     => (string)$product->getName(),
                ];
            }
            if (!$items) {
                return null;
            }

            return [
                '@type'           => 'ItemList',
                '@id'             => $category->getUrl() . '#itemlist',
                'name'            => (string)$category->getName(),
                'numberOfItems'   => count($items),
                'itemListElement' => $items,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
