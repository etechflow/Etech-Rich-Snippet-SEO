<?php

declare(strict_types=1);

namespace Etechflow\RichSnippets\Controller\Adminhtml\License;

use Etechflow\RichSnippets\Model\LicenseValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Cache\Type\Config as ConfigCacheType;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\View\Result\PageFactory;

/**
 * Landing page after payment. Two providers, two verification calls — both end
 * with the portal handing back a license key which is saved to config:
 *   Stripe : ?session_id=... -> POST /license/activate
 *   PayPal : ?token=<orderID>&method=paypal&sub_id=... -> POST /payment/paypal/capture
 * The portal verifies payment with ITS OWN keys; Magento never holds them.
 */
class Activated extends Action
{
    public const ADMIN_RESOURCE = 'Etechflow_RichSnippets::config';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly CurlFactory $curlFactory,
        private readonly WriterInterface $configWriter,
        private readonly CacheInterface $cache,
        private readonly LicenseValidator $licenseValidator
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $method = strtolower(trim((string) $this->getRequest()->getParam('method', '')));
        $token  = trim((string) $this->getRequest()->getParam('token', '')); // PayPal order id
        $isPaypal = ($method === 'paypal' || $token !== '');

        $portal = rtrim(str_replace('/license/validate', '', $this->licenseValidator->getPortalUrl()), '/');

        $licenseKey = '';
        $planName   = '';
        $error      = '';

        if ($isPaypal) {
            [$licenseKey, $planName, $error] = $this->capturePaypal($portal, $token);
        } else {
            $sessionId = trim((string) $this->getRequest()->getParam('session_id', ''));
            if (!$sessionId) {
                $this->messageManager->addErrorMessage(__('Invalid payment callback.'));
                return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('etechflow_richsnippets/license/gate');
            }
            [$licenseKey, $planName, $error] = $this->activateStripe($portal, $sessionId);
        }

        if ($licenseKey) {
            $this->configWriter->save(LicenseValidator::XML_PATH_LICENSE_KEY, $licenseKey);
            $this->cache->clean([ConfigCacheType::CACHE_TAG]);
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->prepend(__('Subscription Activated'));

        $block = $page->getLayout()->getBlock('etechflow.richsnippets.license.activated');
        if ($block) {
            $block->setData('license_key', $licenseKey)
                  ->setData('plan', $planName)
                  ->setData('error', $error)
                  ->setData('settings_url', $this->getUrl('adminhtml/system_config/edit/section/etechflow_richsnippets'))
                  ->setData('management_url', $this->getUrl('etechflow_richsnippets/license/gate'));
        }

        return $page;
    }

    /**
     * @return array{0:string,1:string,2:string} [licenseKey, planName, error]
     */
    private function activateStripe(string $portal, string $sessionId): array
    {
        $subId  = trim((string) $this->getRequest()->getParam('sub_id', ''));
        $plan   = trim((string) $this->getRequest()->getParam('plan', ''));
        $domain = trim((string) $this->getRequest()->getParam('domain', '')) ?: $this->licenseValidator->getCurrentHost();
        $name   = trim((string) $this->getRequest()->getParam('name', ''));
        $email  = trim((string) $this->getRequest()->getParam('email', ''));

        $payload = json_encode(array_filter([
            'session_id' => $sessionId,
            'sub_id'     => $subId ?: null,
            'domain'     => $domain,
            'name'       => $name,
            'email'      => $email,
            'plan'       => $plan,
        ]));

        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout(25);
            $curl->addHeader('Content-Type', 'application/json');
            $curl->addHeader('Accept', 'application/json');
            $curl->post($portal . '/license/activate', $payload);
            $status = (int) $curl->getStatus();
            $body   = (string) $curl->getBody();
            $data   = json_decode($body, true);

            if ($status === 200 && !empty($data['license_key'])) {
                return [(string) $data['license_key'], (string) ($data['plan'] ?? $plan), ''];
            }
            $error = is_array($data) && !empty($data['error']) ? $data['error'] : ('Portal returned status ' . $status . ': ' . $body);
            return ['', '', $error];
        } catch (\Throwable $e) {
            return ['', '', 'Could not reach portal: ' . $e->getMessage()];
        }
    }

    /**
     * @return array{0:string,1:string,2:string} [licenseKey, planName, error]
     */
    private function capturePaypal(string $portal, string $token): array
    {
        if ($token === '') {
            return ['', '', 'Invalid PayPal callback (missing order token).'];
        }
        $subId = trim((string) $this->getRequest()->getParam('sub_id', ''));
        $plan  = trim((string) $this->getRequest()->getParam('plan', ''));

        $payload = json_encode(['orderID' => $token, 'sub_id' => $subId]);

        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout(25);
            $curl->addHeader('Content-Type', 'application/json');
            $curl->addHeader('Accept', 'application/json');
            $curl->post($portal . '/payment/paypal/capture', $payload);
            $status = (int) $curl->getStatus();
            $body   = (string) $curl->getBody();
            $data   = json_decode($body, true);

            if ($status === 200 && !empty($data['success']) && !empty($data['license_key'])) {
                return [(string) $data['license_key'], (string) ($data['plan'] ?? $plan), ''];
            }
            $error = is_array($data) && !empty($data['error'])
                ? $data['error']
                : ('PayPal capture did not complete (status ' . $status . '): ' . $body);
            return ['', '', $error];
        } catch (\Throwable $e) {
            return ['', '', 'Could not reach portal: ' . $e->getMessage()];
        }
    }
}
