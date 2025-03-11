<?php

namespace Whitepay\Payment\Block\Form;

use Magento\Payment\Block\Form as PaymentForm;

/**
 * Class for Whitepay payment method form
 */
class Whitepay extends PaymentForm
{
    /** @var string */
    protected $_template = 'Whitepay_Payment::form/whitepay_form.phtml';

    /**
     * Get instructions text from config
     *
     * @return string
     */
    public function getInstructions()
    {
        return $this->getMethod()->getConfigData('instructions');
    }
}