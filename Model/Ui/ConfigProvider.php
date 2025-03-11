<?php

namespace Whitepay\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Helper\Data as PaymentHelper;

class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'whitepay';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * ConfigProvider constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        PaymentHelper $paymentHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $config = [];
        $methodInstance = $this->paymentHelper->getMethodInstance(self::CODE);
        if ($methodInstance->isAvailable()) {
            $config['payment'][self::CODE] = [
                'instructions' => $this->scopeConfig->getValue('payment/whitepay/instructions', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
            ];
        }
        return $config;
    }
}