<?php

declare(strict_types=1);

namespace Etechflow\RichSnippets\Controller\Adminhtml\License;

use Etechflow\RichSnippets\Model\LicenseValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\HTTP\Client\CurlFactory;

/**
 * Starts checkout via the eTechFlow webstore Paddle broker (module.etechflow.com)
 * — the same gateway Mega Menu uses. The broker opens a Paddle transaction on the
 * webstore's OWN Paddle account and returns the hosted pay URL; the licensing
 * portal still issues the SP-XXXX key once payment clears. No card keys in Magento.
 */
class Checkout extends Action
{
    public const ADMIN_RESOURCE = 'Etechflow_RichSnippets::config';

    private const MODULE_ID = 'rich-snippets';
    private const BROKER_URL = 'https://module.etechflow.com/api/license/checkout';
    private const LICENSE_TOKEN = 'lcsk_8f3b9d2a7c14e605b9af2e7c1d8043f6';

    public function __construct(
        Context $context,
        private readonly CurlFactory $curlFactory,
        private readonly LicenseValidator $licenseValidator
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $plan   = trim((string) $this->getRequest()->getPost('plan', ''));
        $name   = trim((string) $this->getRequest()->getPost('name', ''));
        $email  = trim((string) $this->getRequest()->getPost('email', ''));
        $domain = $this->licenseValidator->getCurrentHost();

        $gate = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('etechflow_richsnippets/license/gate');

        if (!$plan) {
            $this->messageManager->addErrorMessage(__('Invalid plan selected.'));
            return $gate;
        }
        if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->messageManager->addErrorMessage(__('Please enter a valid name and email address.'));
            return $gate;
        }

        $payload = json_encode([
            'plan'             => $plan,
            'name'             => $name,
            'email'            => $email,
            'domain'           => $domain,
            'module'           => self::MODULE_ID,
            'magento_callback' => $this->getUrl('etechflow_richsnippets/license/activated'),
            'magento_cancel'   => $this->getUrl('etechflow_richsnippets/license/gate'),
        ]);

        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout(25);
            $curl->addHeader('Content-Type', 'application/json');
            $curl->addHeader('Accept', 'application/json');
            $curl->addHeader('X-ETF-License-Token', self::LICENSE_TOKEN);
            $curl->post(self::BROKER_URL, $payload);
            $status = (int) $curl->getStatus();
            $body   = (string) $curl->getBody();
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Could not reach the licensing portal. Please try again.'));
            return $gate;
        }

        $data = json_decode($body, true);
        if ($status === 200 && !empty($data['url'])) {
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setUrl((string) $data['url']);
        }

        $err = is_array($data) && !empty($data['error']) ? $data['error'] : ('Portal returned status ' . $status);
        $this->messageManager->addErrorMessage(__('Checkout error: %1', $err));
        return $gate;
    }
}
