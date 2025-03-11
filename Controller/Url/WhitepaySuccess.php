<?php

namespace Whitepay\Payment\Controller\Url;

use Magento\Framework\App\Action\Action;
use Magento\Sales\Model\Order;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Whitepay\Payment\Model\Whitepay;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class WhitepaySuccess
 */
class WhitepaySuccess extends Action implements CsrfAwareActionInterface
{
    /** @var PageFactory */
    private $resultPageFactory;

    /** @var JsonFactory */
    private $jsonResultFactory;

    /**
     * WhitepaySuccess constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $jsonResultFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        PageFactory $resultPageFactory,
        JsonFactory $jsonResultFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonResultFactory = $jsonResultFactory;
        parent::__construct($context);
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool
     */
    public function validateForCsrf(RequestInterface $request): bool
    {
        return true;
    }

    /**
     * Get request from Whitepay
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     * @throws LocalizedException
     */
    public function execute()
    {
        $payload = $this->getRequest()->getContent();
        if (empty($payload)) {
            $payload = file_get_contents("php://input");
        }

        if (empty($payload)) {
            throw new LocalizedException(__('Request Parameter is not matched.'));
        }

        $paymentMethod = $this->_objectManager->get(Whitepay::class);
        $response = $paymentMethod->processResponse($payload);

        $result = $this->jsonResultFactory->create();
        if ($response !== 'OK') {
            $result->setHttpResponseCode(400);
        }

        $result->setData(['result' => $response]);

        return $result;
    }
}