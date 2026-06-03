<?php
declare(strict_types=1);

namespace Etechflow\RichSnippets\Block;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Etechflow\RichSnippets\ViewModel\Config;
use Etechflow\RichSnippets\ViewModel\CmsPage;
use Etechflow\RichSnippets\ViewModel\Site;

class Cms extends AbstractBlock
{
    public function __construct(
        Context $context,
        private Config $config,
        private CmsPage $cmsVm,
        private Site $site,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->config->isEnabled('cms')) {
            return '';
        }
        $page = $this->cmsVm->getNode();
        if (!$page) {
            return '';
        }
        $graph = [$page, $this->site->getOrganizationNode(), $this->site->getWebsiteNode()];
        $json = json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json ? '<script type="application/ld+json">' . $json . '</script>' : '';
    }
}
