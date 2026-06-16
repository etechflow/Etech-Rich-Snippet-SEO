<?php

declare(strict_types=1);

namespace Etechflow\RichSnippets\Block\Adminhtml\System\Config;

use Etechflow\RichSnippets\Model\LicenseValidator;
use Magento\Backend\Block\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\Js;

/**
 * Status banner at the top of the Rich Snippets admin config section.
 *
 * The module is gated only by the licence — a valid key is ALWAYS required
 * (no environment toggle, no host bypass). Surfaces whether the module is
 * active or sitting locked, and why.
 */
class ModuleStatus extends Fieldset
{
    public function __construct(
        Context $context,
        Session $authSession,
        Js $jsHelper,
        private readonly LicenseValidator $licenseValidator,
        array $data = []
    ) {
        parent::__construct($context, $authSession, $jsHelper, $data);
    }

    public function render(AbstractElement $element)
    {
        $element->addClass('etechflow-module-status');

        $html  = $this->_getHeaderHtml($element);
        $html .= '<tr id="' . $element->getHtmlId() . '_status_row"><td colspan="4">';
        $html .= $this->renderStatusBanner();
        $html .= '</td></tr>';
        $html .= $this->_getFooterHtml($element);

        return $html;
    }

    protected function _isCollapseState($element)
    {
        return true;
    }

    private function renderStatusBanner(): string
    {
        $host = $this->licenseValidator->getCurrentHost();
        $licenceValid = $this->licenseValidator->isValid();
        $hasKey = trim($this->licenseValidator->getConfiguredKey()) !== ''
            || trim($this->licenseValidator->getConfiguredBundleKey()) !== '';

        if (!$licenceValid) {
            if (!$hasKey) {
                return $this->banner(
                    'warning',
                    '&#9888;&#65039; Licence key missing',
                    'No licence key has been entered for host <code>' . $this->escapeHtml($host) . '</code>. '
                    . 'The module is locked — <strong>no</strong> schema.org JSON-LD or OpenGraph/Twitter meta is '
                    . 'emitted on the storefront, regardless of the switches below. '
                    . 'Paste your key in the <strong>License Key</strong> field below, or visit '
                    . '<a href="' . $this->getUrl('etechflow_richsnippets/license/gate') . '" style="color:inherit;text-decoration:underline;">License &amp; Plans</a> '
                    . 'to purchase one.'
                );
            }

            return $this->banner(
                'warning',
                '&#9888;&#65039; Licence key invalid for this host',
                'A licence key has been entered, but it does not match the expected key for host '
                . '<code>' . $this->escapeHtml($host) . '</code>. The module is locked. '
                . 'Common causes: server IP removed from the portal subscription, wrong key, site moved domains, '
                . 'key suspended, or stray whitespace. <code>www.</code> is normalised — one key works for both.'
            );
        }

        return $this->banner(
            'success',
            '&#9989; Module is active',
            'Licence valid for <code>' . $this->escapeHtml($host) . '</code>. Rich-snippet structured data '
            . '(schema.org JSON-LD) and OpenGraph/Twitter meta are emitted on the storefront per the switches below. '
            . '<em>Note: the master switch below must also be ON.</em>'
        );
    }

    private function banner(string $kind, string $heading, string $body): string
    {
        $palette = match ($kind) {
            'success' => ['bg' => '#e7f5ec', 'border' => '#2e7d32', 'fg' => '#1b5e20'],
            'warning' => ['bg' => '#fff4e5', 'border' => '#ef6c00', 'fg' => '#bf360c'],
            'info'    => ['bg' => '#e3f2fd', 'border' => '#1976d2', 'fg' => '#0d47a1'],
            default   => ['bg' => '#f5f5f5', 'border' => '#9e9e9e', 'fg' => '#424242'],
        };

        return sprintf(
            '<div style="background:%s;border-left:4px solid %s;color:%s;padding:14px 18px;margin:0 0 6px;border-radius:4px;font-size:13px;line-height:1.5;">'
            . '<strong style="font-size:14px;display:block;margin-bottom:4px;">%s</strong>%s'
            . '</div>',
            $palette['bg'],
            $palette['border'],
            $palette['fg'],
            $heading,
            $body
        );
    }
}
