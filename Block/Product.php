<?php
declare(strict_types=1);

namespace Etechflow\RichSnippets\Block;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Etechflow\RichSnippets\ViewModel\Config;
use Etechflow\RichSnippets\ViewModel\Product as ProductVm;
use Etechflow\RichSnippets\ViewModel\Site;
use Etechflow\RichSnippets\ViewModel\Breadcrumbs;

class Product extends AbstractBlock
{
    public function __construct(
        Context $context,
        private Config $config,
        private ProductVm $productVm,
        private Site $site,
        private Breadcrumbs $crumbs,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->config->isEnabled('product')) {
            return '';
        }
        $node = $this->productVm->getNode();
        if (!$node) {
            return '';
        }
        $graph = [$node];
        if ($bc = $this->crumbs->getNode()) {
            $graph[] = $bc;
        }
        $graph[] = $this->site->getOrganizationNode();
        $graph[] = $this->site->getWebsiteNode();
        $json = json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json ? '<script type="application/ld+json">' . $json . '</script>' : '';
    }
}
