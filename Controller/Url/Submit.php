<?php

namespace Whitepay\Payment\Controller\Url;

use Magento\Framework\App\Action\Action;
use Magento\Sales\Model\Order;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Checkout\Model\Session;
use Whitepay\Payment\Block\Widget\Redirect;

/**
 * Class Submit
 */
class Submit extends Action implements CsrfAwareActionInterface
{
    /** @var PageFactory */
    private $resultPageFactory;

    /** @var Redirect */
    private $whitepay;

    /** @var Session */
    private $checkoutSession;

    /** @var bool */
    private $isScopePrivate;

    /**
     * Submit constructor
     * @param \Magento\Framework\App\Action\Context $context
     * @param Session $checkoutSession
     * @param PageFactory $resultPageFactory
     * @param Redirect $whitepay_form
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        Session $checkoutSession,
        PageFactory $resultPageFactory,
        Redirect $whitepay_form
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->checkoutSession = $checkoutSession;
        $this->whitepay = $whitepay_form;
        $this->isScopePrivate = true;
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
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface|\Magento\Framework\View\Result\Page|void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Validator\Exception
     */
    public function execute()
    {
        $data = $this->getRequest()->getParams();
        $preparedOrderData = $this->whitepay->getPreparedOrderData($data);

        if (isset($preparedOrderData['error'])){
            $message = $preparedOrderData['error'];
            $this->messageManager->addErrorMessage($message);

            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        $request = $this->whitepay->doRequest($preparedOrderData);
        $whitepayOrder = $request['order'];

        if (isset($whitepayOrder['acquiring_url'])) {
            return $this->_redirect->redirect($this->_response, $whitepayOrder['acquiring_url']);
        } else {
            $this->restoreCart();
        }

        $page = $this->resultPageFactory->create();

        return $page;
    }

    /**
     * Restore cart
     * @return void
     */
    private function restoreCart(): void
    {
        $lastQuoteId = $this->checkoutSession->getLastQuoteId();
        if ($quote = $this->_objectManager->get(\Magento\Quote\Model\Quote::class)->loadByIdWithoutStore($lastQuoteId)) {
            $quote->setIsActive(true)
                ->setReservedOrderId(null)
                ->save();
            $this->checkoutSession->setQuoteId($lastQuoteId);
        }

        $message = __('Payment failed. Please try again.');
        $this->messageManager->addErrorMessage($message);
    }
}