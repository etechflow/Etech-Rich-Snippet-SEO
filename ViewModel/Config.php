<?php
declare(strict_types=1);

namespace Etechflow\RichSnippets\ViewModel;

use Etechflow\RichSnippets\Model\LicenseValidator;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config implements ArgumentInterface
{
    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private LicenseValidator $licenseValidator
    ) {
    }

    public function isEnabled(string $area = 'general'): bool
    {
        // A valid licence is ALWAYS required (PORTAL_LICENSING_GUIDE §0 — no host
        // bypass). This is the single storefront chokepoint: every Block calls
        // isEnabled(), so an unlicensed module emits no schema / OG meta anywhere.
        if (!$this->licenseValidator->isValid()) {
            return false;
        }
        if (!$this->scopeConfig->isSetFlag('etechflow_richsnippets/general/enabled', ScopeInterface::SCOPE_STORE)) {
            return false;
        }
        if ($area === 'general') {
            return true;
        }
        return $this->scopeConfig->isSetFlag('etechflow_richsnippets/' . $area . '/enabled', ScopeInterface::SCOPE_STORE);
    }
}
