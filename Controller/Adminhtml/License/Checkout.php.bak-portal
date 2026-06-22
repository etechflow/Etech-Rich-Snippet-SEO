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
 * Starts checkout by delegating to the eTechFlow licensing portal. The portal
 * creates the payment session using ITS OWN keys (portal admin -> Settings) and
 * returns the redirect URL. No Stripe/PayPal keys live in Magento.
 *
 * The POSTed `method` selects the portal endpoint — stripe -> create-session,
 * paypal -> create-order; both take the same payload and return {"url": ...}.
 */
class Checkout extends Action
{
    public const ADMIN_RESOURCE = 'Etechflow_RichSnippets::config';

    private const MODULE_ID = 'rich-snippets';

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
        $method = strtolower(trim((string) $this->getRequest()->getPost('method', 'stripe')));
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

        $endpoint = $method === 'paypal'
            ? '/payment/paypal/create-order'
            : '/payment/stripe/create-session';

        $portalBase = rtrim(str_replace('/license/validate', '', $this->licenseValidator->getPortalUrl()), '/');
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
            $curl->setTimeout(20);
            $curl->addHeader('Content-Type', 'application/json');
            $curl->addHeader('Accept', 'application/json');
            $curl->post($portalBase . $endpoint, $payload);
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
