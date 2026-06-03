<?php
declare(strict_types=1);

namespace Etechflow\RichSnippets\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;

/**
 * BreadcrumbList builder for both product pages (Home > path > product) and
 * category pages (Home > path), derived from the category tree.
 */
class Breadcrumbs implements ArgumentInterface
{
    public function __construct(
        private Registry $registry,
        private StoreManagerInterface $storeManager,
        private CategoryRepositoryInterface $categoryRepository
    ) {
    }

    public function getNode(): ?array
    {
        $product = $this->registry->registry('current_product');
        if (!$product || !$product->getId()) {
            return null;
        }
        try {
            $store = $this->storeManager->getStore();
            [$items, $pos] = $this->trailFromCategory($this->deepestCategory($product), $store);
            $items[] = ['@type' => 'ListItem', 'position' => $pos,
                        'name' => (string)$product->getName(), 'item' => $product->getProductUrl()];
            return [
                '@type'           => 'BreadcrumbList',
                '@id'             => $product->getProductUrl() . '#breadcrumb',
                'itemListElement' => $items,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getCategoryNode(): ?array
    {
        $category = $this->registry->registry('current_category');
        if (!$category || !$category->getId()) {
            return null;
        }
        try {
            $store = $this->storeManager->getStore();
            if ((int)$category->getId() === (int)$store->getRootCategoryId()) {
                return null;
            }
            [$items] = $this->trailFromCategory($category, $store);
            return [
                '@type'           => 'BreadcrumbList',
                '@id'             => $category->getUrl() . '#breadcrumb',
                'itemListElement' => $items,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function deepestCategory($product)
    {
        $col = $product->getCategoryCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToSort('level', 'desc')
            ->setPageSize(1);
        $cat = $col->getFirstItem();
        return ($cat && $cat->getId()) ? $cat : null;
    }

    /**
     * @return array{0: array, 1: int} list of breadcrumb items + next position
     */
    private function trailFromCategory($category, $store): array
    {
        $base   = $store->getBaseUrl();
        $items  = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base]];
        $pos    = 2;
        if (!$category) {
            return [$items, $pos];
        }
        $rootId  = (int)$store->getRootCategoryId();
        $started = false;
        foreach (explode('/', (string)$category->getPath()) as $cid) {
            $cid = (int)$cid;
            if ($cid === $rootId) {
                $started = true;
                continue;
            }
            if (!$started) {
                continue;
            }
            try {
                $cat = $this->categoryRepository->get($cid, (int)$store->getId());
            } catch (\Throwable $e) {
                continue;
            }
            if (!$cat->getIsActive()) {
                continue;
            }
            $items[] = ['@type' => 'ListItem', 'position' => $pos++,
                        'name' => (string)$cat->getName(), 'item' => $cat->getUrl()];
        }
        return [$items, $pos];
    }
}
