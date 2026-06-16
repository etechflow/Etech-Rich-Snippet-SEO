<?php

declare(strict_types=1);

namespace Etechflow\RichSnippets\Controller\Adminhtml\License;

use Etechflow\RichSnippets\Model\LicenseValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * License-required gate page. Shows plan cards + "Enter License Key".
 * Redirects to the module's config section when the licence is already valid
 * (this module has no admin grid — its surface is the storefront + config).
 */
class Gate extends Action
{
    public const ADMIN_RESOURCE = 'Etechflow_RichSnippets::config';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly LicenseValidator $licenseValidator
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        if ($this->licenseValidator->isValid()) {
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $redirect->setPath('adminhtml/system_config/edit/section/etechflow_richsnippets');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->prepend(__('Rich Snippets — License Required'));
        return $page;
    }
}
