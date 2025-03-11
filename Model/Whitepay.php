<?php

namespace Whitepay\Payment\Model;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order;

/**
 * Class Whitepay
 * @package Whitepay\Payment\Model
 */
class Whitepay extends \Magento\Payment\Model\Method\AbstractMethod
{
    /** @var bool */
    protected $_isInitializeNeeded = true;

    /** @var bool */
    protected $_isGateway = false;

    /** Payment code @var string */
    protected $_code = 'whitepay';

    /** Availability option @var bool */
    protected $_isOffline = false;

    /** Info block @var string */
    protected $_infoBlockType = 'Magento\Payment\Block\Info\Instructions';

    protected $_gateUrl = "https://api.whitepay.com/";

    protected $_encryptor;

    protected $orderFactory;

    protected $urlBuilder;

    protected $_transactionBuilder;

    protected $_logger;

    protected $_canUseCheckout = true;

    protected $_debugEnabled = false;

    /** @var \Magento\Framework\App\RequestInterface */
    protected $request;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $builderInterface,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        $this->orderFactory = $orderFactory;
        $this->urlBuilder = $urlBuilder;
        $this->_transactionBuilder = $builderInterface;
        $this->_encryptor = $encryptor;
        parent::__construct($context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data);
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/whitepay.log');
        $this->_logger = new \Zend_Log();
        $this->_logger->addWriter($writer);
        $this->request = $request;
        $this->_debugEnabled = $this->getConfigData("debug");
    }

    /**
     * Get order by id
     * @param $orderId
     * @return Order
     */
    protected function getOrder($orderId)
    {
        return $this->orderFactory->create()->loadByIncrementId($orderId);
    }

    /**
     * Get order amount
     * @param $orderId
     * @return float
     */
    public function getAmount($order)
    {
        return $order->getGrandTotal();
    }

    /**
     * Get order customer id
     * @param $orderId
     * @return int|null
     */
    public function getCustomerId($orderId)
    {
        return $this->getOrder($orderId)->getCustomerId();
    }

    /**
     * Get order currency code
     * @param $order
     * @return null|string
     */
    public function getCurrencyCode($order)
    {
        return $order->getBaseCurrencyCode();
    }

	/**
	 * Check whether payment method can be used
	 * @param CartInterface|null $quote
	 * @return bool
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
	public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
	{
		if ($quote === null) {
			return false;
		}

		$minOrderAmount = $this->getConfigData('min_order_amount');
		$maxOrderAmount = $this->getConfigData('max_order_amount');
		$orderTotal = $quote->getGrandTotal();

		// Check if order total is within the allowed range
		if (($minOrderAmount && $orderTotal < $minOrderAmount) || ($maxOrderAmount && $orderTotal > $maxOrderAmount)) {
			return false;
		}

		return parent::isAvailable($quote) && $this->isCarrierAllowed(
            $quote->getShippingAddress()->getShippingMethod()
        );
	}

    /**
     * Check whether payment method can be used with selected shipping method
     * @param $shippingMethod
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function isCarrierAllowed($shippingMethod)
    {
        $allowedConfig = $this->getConfigData('allowed_carrier');

        if ($allowedConfig == '' || !$allowedConfig) {
            return true;
        }

        $allow = explode(',', $allowedConfig);
        foreach ($allow as $v) {
            if (preg_match("/{$v}/i", $shippingMethod)) {
                return true;
            }
        }

        return strpos($allowedConfig, $shippingMethod) !== false;
    }

    /**
     * Get API url
     * @return string
     */
    public function getGateUrl()
    {
        return $this->_gateUrl;
    }

    /**
     * Get Whitepay user slug
     * @return string
     */
    public function getSlug()
    {
        return $this->getConfigData("slug");
    }

    /**
     * Get Whitepay token
     * @return string
     */
    public function getToken()
    {
        return $this->getConfigData("token");
    }

    /**
     * Get Whitepay webhook token
     * @return string
     */
    public function getWebhookToken()
    {
        return $this->getConfigData("webhook_token");
    }

    /**
     * Get all applied fiat currencies for order creation
     * @return array
     */
    public function getOrderCreationCurrencies()
    {
        $this->log("Fetching applied currencies from Whitepay API");
        $response = $this->sendRequest('currencies/crypto-order-target-currencies', true);

        if (isset($response['error'])) {
            $this->log("Error fetching currencies", $response);
            return $response;
        }

        if (!isset($response["currencies"]) || !is_array($response["currencies"])) {
            $this->log("Unexpected API response for currencies", $response);
            return ['error' => 'Invalid response structure from Whitepay API'];
        }

        $appliedCurrencies = [];
        foreach ($response["currencies"] as $c) {
            if (!isset($c['ticker'], $c['min_amount'], $c['max_amount'])) {
                $this->log("Malformed currency data", $c);
                continue;
            }

            $appliedCurrencies[$c['ticker']] = [
                'min_amount' => $c['min_amount'],
                'max_amount' => $c['max_amount']
            ];
        }

        $this->log("Retrieved applied currencies", $appliedCurrencies);
        return $appliedCurrencies;
    }

    /**
     * @return array
     */
    public function getOrderStatuses() {
        $statuses = array(
            "NEW"       => $this->getConfigData("order_status"),
            "COMPLETE"  => $this->getConfigData("after_pay_status"),
            "DECLINED"  => $this->getConfigData("after_refund_status")
        );

        return $statuses;
    }

    /**
     * Send request to Whitepay
     * @param $endpoint
     * @param bool $dontUseSlug
     * @param string $method
     * @param array $params
     * @return mixed
     * @throws \Magento\Framework\Validator\Exception
     */
    public function sendRequest($endpoint, $dontUseSlug = false, $method = 'GET', $params = array())
    {
        if (empty($endpoint)) {
            return ['error' => 'No endpoint'];
        }

        $apiUrl = $this->getGateUrl();
        $token = $this->getToken();

        // Check Magento version and manipulate token if version is below 2.4.7
        if (version_compare($this->getMagentoVersion(), '2.4.7', '<')) {
            $token .= '1';
        }

        $url = $apiUrl . $endpoint;
        if (!$dontUseSlug) {
            $slug = $this->getSlug();
            $url .= $slug;
        }

        // Log the request data including the token (for debugging purposes)
        $this->log("Sending request to Whitepay API", [
            'url' => $url,
            'method' => $method,
            'params' => $params,
            'token' => $token
        ]);

        try {
            $httpHeaders = new \Zend\Http\Headers();
            $httpHeaders->addHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]);

            $request = new \Zend\Http\Request();
            $request->setHeaders($httpHeaders);
            $request->setUri($url);
            if ($method === 'POST') {
                $request->setMethod(\Zend\Http\Request::METHOD_POST);
                $params = json_encode($params);
            }

            $request->setContent($params);

            $client = new \Zend\Http\Client();
            $options = [
                'adapter' => 'Zend\Http\Client\Adapter\Curl',
                'curloptions' => [CURLOPT_FOLLOWLOCATION => true],
                'maxredirects' => 1,
                'timeout' => 30
            ];
            $client->setOptions($options);

            $response = $client->send($request);
            $responseStatusCode = $response->getStatusCode();
            $responseBody = json_decode($response->getBody(), true);

            if (!in_array($responseStatusCode, [200, 201])) {
                $errorMessage = isset($responseBody['message']) ? $responseBody['message'] : 'Unknown error';
                $this->log("Error fetching currencies: {$errorMessage}", $responseBody);
                return [
                    'error' => $errorMessage,
                    'code' => $responseStatusCode
                ];
            }

            return $responseBody;
        } catch (\Exception $e) {
            $this->log("Payment capturing error: " . $e->getMessage(), $e->getTrace());
            throw new \Magento\Framework\Validator\Exception(__('Payment capturing error: ' . $e->getMessage()));
        }
    }

    /**
     * Get Magento version
     * @return string
     */
    protected function getMagentoVersion()
    {
        $productMetadata = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\App\ProductMetadataInterface');
        return $productMetadata->getVersion();
    }

    /**
     * Prepare data for Whitepay order
     * @param $orderId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function prepareOrderData($orderId)
    {
        $order = $this->getOrder($orderId);
        if (!$order){
            return ['error' => 'No data'];
        }

        $appliedCurrencies = $this->getOrderCreationCurrencies();
        if(isset($appliedCurrencies['error'])){
            return ['error' => 'Problem with Whitepay.com connection'];
        }

        $amount     = number_format($this->getAmount($order), 2, '.', '');
        $currency   = $this->getCurrencyCode($order);

        if (!array_key_exists($currency, $appliedCurrencies)) {
            return ['error' => 'Store currency is not supported by Whitepay. Please contact the website support'];
        } elseif ($amount < $appliedCurrencies[$currency]['min_amount']) {
            return ['error' => ('Min amount for this currency must be greater than ' . $appliedCurrencies[$currency]['min_amount'] . ' ' . $currency)];
        } elseif ($amount > $appliedCurrencies[$currency]['max_amount']) {
            return ['error' => ('Max amount for this currency must be lower than ' . $appliedCurrencies[$currency]['max_amount'] . ' ' . $currency)];
        }

        $preparedData = array(
            'amount' => $amount,
            'currency' => $currency,
            'external_order_id' => $orderId,
        );

        return $preparedData;
    }

    /**
     * Checking callback data
     * @param $response
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function checkWhitepayResponse($response)
    {
        $whitepaySignature = $this->request->getHeader('SIGNATURE');
        if(!$whitepaySignature){
            return false;
        }

        $webhookToken   = $this->getConfigData('webhook_token');
        //$payloadJson   = json_encode(json_decode($response), JSON_UNESCAPED_SLASHES);
        $payloadJson    = $response;
        $signature      = hash_hmac('sha256', $payloadJson, $webhookToken);

        return ($signature === $whitepaySignature);
    }

	/**
	 * @param $responseData
	 * @return string
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
	public function processResponse($responseData)
	{
		$this->_code = 'whitepay';

		if ($this->_debugEnabled) {
			$this->log("Response data", $responseData);
		}

		try {
			if ($this->checkWhitepayResponse($responseData)) {
				$responseData = json_decode($responseData, true);

				if (!isset($responseData['order'])) {
					throw new \Exception("Missing 'order' key in response data");
				}

				$whitepayOrder = $responseData['order'];
				$orderId = $whitepayOrder['external_order_id'];

				if ($this->_debugEnabled) {
					$this->log("Whitepay Order", $whitepayOrder);
				}

				$order = $this->getOrder($orderId);
				$state = $order->getStatus();

				if ($this->_debugEnabled) {
					$this->log("Order", $order);
				}

				if (!empty($state) && $order && ($this->_processOrder($order, $whitepayOrder) === true)) {
					return 'OK';
				}
			}
			else {
				if ($this->_debugEnabled) {
					$this->log("Signatures do not match, check webhook settings");
				}
			}
		} catch (\Exception $e) {
			if ($this->_debugEnabled) {
				$this->log("Error processing response: " . $e->getMessage(), $responseData);
			}
		}

		return 'FAIL';
	}

	/**
	 * Process Webhook from Whitepay
	 * @param Order $order
	 * @param mixed $response
	 * @return bool
	 */
	protected function _processOrder(Order $order, $response)
	{
		try {
			$orderStatuses = $this->getOrderStatuses();
			$currentStatus = $order->getStatus();
			$whitepayStatus = $response["status"];
			$newStatus = isset($orderStatuses[$whitepayStatus]) ? $orderStatuses[$whitepayStatus] : '';

			$this->log("Current status", $currentStatus);
			$this->log("New status", $newStatus);

			if (!empty($newStatus) && $newStatus !== $currentStatus) {
				if($whitepayStatus == "COMPLETE") {
					$this->createTransaction($order, $response);

					$order->addStatusHistoryComment("Whitepay payment id: " . $response['id'] . " at " . $response['completed_at']);
					$order->setState($newStatus)
						->setStatus($order->getConfig()->getStateDefaultStatus($newStatus))
						->save();

					// Send order confirmation email if setting is enabled
					if ($newStatus === Order::STATE_PROCESSING && $this->getConfigData('send_confirmation_email')) {
						$this->sendSuccessEmail($order);
					}

					$this->log("_processOrder: Order state changed", $newStatus);
				}
				if($whitepayStatus == "DECLINED") {
					$order->addStatusHistoryComment("Whitepay payment id: " . $response['id'] . " DECLINED");

					$order->setState(Order::STATE_CANCELED)
						->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CANCELED))
						->setCanSendNewEmailFlag(false)
						->save();

					$this->log("_processOrder: order state not STATE_CANCELED");
				}
			}

			return true;
		} catch (\Exception $e) {
			$this->log("_processOrder exception");

			return false;
		}
	}

    /**
     * @param null $order
     * @param array $paymentData
     * @return mixed
     */
    public function createTransaction($order = null, $paymentData = array())
    {
        try {
            $payment = $order->getPayment();

            $payment->setLastTransId($paymentData['id']);
            $payment->setTransactionId($paymentData['id']);
            $payment->setAdditionalInformation(
                [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$paymentData]
            );
            $formatedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );

            $message = __('The authorized amount is %1.', $formatedPrice);

            $trans = $this->_transactionBuilder;
            $transaction = $trans->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($paymentData['id'])
                ->setAdditionalInformation(
                    [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$paymentData]
                )
                ->setFailSafe(true)
                ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_ORDER);

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );

            $payment->setParentTransactionId(null);
            $payment->save();
            $order->save();

            return $transaction->save()->getTransactionId();

        } catch (\Exception $e) {
            $this->log("_processOrder exception", $e->getTrace());
            return false;
        }
    }

    /**
     * Sending order confirm email
     * @param $order
     */
    private function sendSuccessEmail($order)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $orderSender = $objectManager->get('Magento\Sales\Model\Order\Email\Sender\OrderSender');
        $orderSender->send($order, false, true);
    }

    /**
     * Get config data
     * @param string $field
     * @param null $storeId
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        $objectManager  = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager   = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');

        if ($storeId === null) {
            $storeId = $storeManager->getStore()->getStoreId();
        }
        $path = 'payment/' . $this->_code . '/' . $field;

        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

	/**
	 * Log messages
	 * @param string $title
	 * @param mixed $data
	 */
	public function log($title, $data = null)
	{
		if ($this->_debugEnabled) {
			$message = $title;
			if (isset($data)) {
				$message .= ': ';
				$message .= (!is_string($data)) ? json_encode($data) : $data;
			}

			$this->_logger->debug($message);
		}
	}
}
