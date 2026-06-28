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
 * Landing page after payment. The buyer returns from the webstore Stripe
 * checkout (module.etechflow.com) carrying the broker session id; we fetch the
 * issued SP-XXXX key from the broker (only returned once Stripe confirms
 * payment) and save it to config. Same gateway as Mega Menu.
 */
class Activated extends Action
{
    public const ADMIN_RESOURCE = 'Etechflow_RichSnippets::config';

    private const BROKER_URL = 'https://module.etechflow.com/api/license/result';
    private const LICENSE_TOKEN = 'lcsk_8f3b9d2a7c14e605b9af2e7c1d8043f6';

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
        $sessionId = trim((string) $this->getRequest()->getParam('session_id', ''));
        $plan      = trim((string) $this->getRequest()->getParam('plan', ''));

        $licenseKey = '';
        $error      = '';

        if ($sessionId === '') {
            $error = 'Invalid payment callback.';
        } else {
            try {
                $curl = $this->curlFactory->create();
                $curl->setTimeout(30);
                $curl->addHeader('Content-Type', 'application/json');
                $curl->addHeader('Accept', 'application/json');
                $curl->addHeader('X-ETF-License-Token', self::LICENSE_TOKEN);
                $curl->post(self::BROKER_URL, json_encode(['session_id' => $sessionId]));
                $status = (int) $curl->getStatus();
                $data   = json_decode((string) $curl->getBody(), true);
                if ($status === 200 && !empty($data['license_key'])) {
                    $licenseKey = (string) $data['license_key'];
                    $plan       = (string) ($data['plan'] ?? $plan);
                } else {
                    $error = is_array($data) && !empty($data['error']) ? $data['error'] : 'Payment not confirmed yet.';
                }
            } catch (\Throwable $e) {
                $error = 'Could not reach the licensing portal: ' . $e->getMessage();
            }
        }

        if ($licenseKey !== '') {
            $this->configWriter->save(LicenseValidator::XML_PATH_LICENSE_KEY, $licenseKey);
            $this->configWriter->save(LicenseValidator::XML_PATH_ISSUED_KEY, $licenseKey);
            $this->configWriter->save(LicenseValidator::XML_PATH_ISSUED_AT, (string) time());
            $this->configWriter->save(LicenseValidator::XML_PATH_IP_BLOCKED, '0');
            $this->cache->clean([ConfigCacheType::CACHE_TAG]);
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->prepend(__('Subscription Activated'));

        $block = $page->getLayout()->getBlock('etechflow.richsnippets.license.activated');
        if ($block) {
            $block->setData('license_key', $licenseKey)
                  ->setData('plan', $plan)
                  ->setData('error', $error)
                  ->setData('settings_url', $this->getUrl('adminhtml/system_config/edit/section/etechflow_richsnippets'))
                  ->setData('management_url', $this->getUrl('etechflow_richsnippets/license/gate'));
        }

        return $page;
    }
}
