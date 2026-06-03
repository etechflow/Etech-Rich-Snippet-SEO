<?php
declare(strict_types=1);

namespace Etechflow\RichSnippets\Block;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Etechflow\RichSnippets\ViewModel\Config;
use Etechflow\RichSnippets\ViewModel\Category as CategoryVm;
use Etechflow\RichSnippets\ViewModel\Site;
use Etechflow\RichSnippets\ViewModel\Breadcrumbs;

class Category extends AbstractBlock
{
    public function __construct(
        Context $context,
        private Config $config,
        private CategoryVm $categoryVm,
        private Site $site,
        private Breadcrumbs $crumbs,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->config->isEnabled('category')) {
            return '';
        }
        $graph = [];
        if ($il = $this->categoryVm->getItemListNode()) {
            $graph[] = $il;
        }
        if ($bc = $this->crumbs->getCategoryNode()) {
            $graph[] = $bc;
        }
        if (!$graph) {
            return '';
        }
        $graph[] = $this->site->getOrganizationNode();
        $graph[] = $this->site->getWebsiteNode();
        $json = json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json ? '<script type="application/ld+json">' . $json . '</script>' : '';
    }
}
