<?php

namespace Whitepay\Payment\Block\Widget;

use \Magento\Framework\View\Element\Template;

/**
 * Сlass Redirect
 */
class Redirect extends Template
{
    /** @var \Whitepay\Payment\Model\Whitepay */
    protected $Config;

    /** @var \Magento\Checkout\Model\Session */
    protected $_checkoutSession;

    /** @var \Magento\Customer\Model\Session */
    protected $_customerSession;

    /** @var \Magento\Sales\Model\OrderFactory */
    protected $_orderFactory;

    /** @var \Magento\Sales\Model\Order\Config */
    protected $_orderConfig;

    /** @var \Magento\Framework\App\Http\Context */
    protected $httpContext;

    /** @var string */
    protected $_template = 'html/whitepay_form.phtml';

    /**
     * Redirect constructor
     * @param Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     * @param \Magento\Framework\App\Http\Context $httpContext
     * @param \Whitepay\Payment\Model\Whitepay $paymentConfig
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Framework\App\Http\Context $httpContext,
        \Whitepay\Payment\Model\Whitepay $paymentConfig,
        \Magento\Sales\Model\OrderRepository $orderRepository,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->_orderFactory = $orderFactory;
        $this->_orderConfig = $orderConfig;
        $this->_isScopePrivate = true;
        $this->httpContext = $httpContext;
        $this->Config = $paymentConfig;
        $this->_orderRepository = $orderRepository;
    }

    /**
     * Get gate url
     * @return null|string
     */
    public function getGateUrl()
    {
        return $this->Config->getGateUrl();
    }

    /**
     * Get order amount
     * @return float|null
     */
    public function getAmount()
    {
        $orderId = $this->_checkoutSession->getLastOrderId();
        if ($orderId) {
            $incrementId = $this->_checkoutSession->getLastRealOrderId();
            return $this->Config->getAmount($incrementId);
        }

        return ['error' => 'No data'];
    }

    /**
     * Get order data from cart and prepare for Whitepay
     * @param $data array
     * @return array|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getPreparedOrderData($data = [])
    {
        $lastOrderId = $this->_checkoutSession->getLastOrderId();

        if ($lastOrderId or isset($data['order'])) {
            $orderId = $this->_checkoutSession->getLastRealOrderId();

            if (!$orderId) {
                $order = $this->_orderRepository->get($data['order']);

                if ($order) {
                    if ($order->getStatus() == 'pending' and $order->getState() == 'new') {
                        $orderId = $order->getIncrementId();
                    }
                }
            }
            if (!$orderId){
                return ['error' => 'No data'];
            }

            return $this->Config->prepareOrderData($orderId);
        }

        return ['error' => 'No data'];
    }

    /**
     * Do request for Create Order to Whitepay API
     * @param $data
     * @return mixed|string[]
     * @throws \Magento\Framework\Validator\Exception
     */
    public function doRequest($data)
    {
        $endpoint   = 'private-api/crypto-orders/';
        $method     = 'POST';

        $response = $this->Config->sendRequest($endpoint, false, $method, $data);
        if(isset($request['order']['acquiring_url'])) {
            $order = $this->_orderRepository->get($data['external_order_id']);
            $order->addStatusHistoryComment("Whitepay payment acquiring url: " . $request['order']['acquiring_url']);
        }

        return $response;
    }
}
