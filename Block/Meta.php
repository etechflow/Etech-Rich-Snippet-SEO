<?php
declare(strict_types=1);

namespace Etechflow\RichSnippets\Block;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Framework\Escaper;
use Etechflow\RichSnippets\ViewModel\Config;
use Etechflow\RichSnippets\ViewModel\OpenGraph;

class Meta extends AbstractBlock
{
    public function __construct(
        Context $context,
        private Config $config,
        private OpenGraph $og,
        private Escaper $escaper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->config->isEnabled('opengraph')) {
            return '';
        }
        $tags = [];
        foreach ($this->og->getOg() as $k => $v) {
            $tags[] = '<meta property="' . $this->escaper->escapeHtmlAttr($k) . '" content="' . $this->escaper->escapeHtmlAttr($v) . '"/>';
        }
        foreach ($this->og->getTwitter() as $k => $v) {
            $tags[] = '<meta name="' . $this->escaper->escapeHtmlAttr($k) . '" content="' . $this->escaper->escapeHtmlAttr($v) . '"/>';
        }
        return $tags ? implode("\n", $tags) . "\n" : '';
    }
}
