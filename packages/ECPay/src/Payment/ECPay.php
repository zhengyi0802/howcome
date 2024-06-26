<?php

namespace ECPay\Payment;

use Webkul\Payment\Payment\Payment;

class ECPay extends Payment
{
    /**
     * Payment method code
     *
     * @var string
     */
    protected $code  = 'ecpay';

    public function getRedirectUrl()
    {
        
    }
}