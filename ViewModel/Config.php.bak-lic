<?php
declare(strict_types=1);

namespace Etechflow\RichSnippets\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config implements ArgumentInterface
{
    public function __construct(private ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(string $area = 'general'): bool
    {
        if (!$this->scopeConfig->isSetFlag('etechflow_richsnippets/general/enabled', ScopeInterface::SCOPE_STORE)) {
            return false;
        }
        if ($area === 'general') {
            return true;
        }
        return $this->scopeConfig->isSetFlag('etechflow_richsnippets/' . $area . '/enabled', ScopeInterface::SCOPE_STORE);
    }
}
