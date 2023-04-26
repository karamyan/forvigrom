<?php

namespace Omnipay\Internal;

use Omnipay\Common\AbstractGateway;

class Gateway extends AbstractGateway
{
    public function getName()
    {
        return 'Internal';
    }

    public function getDefaultParameters()
    {
        return array(
            'token' => env("INTERNAL_TOKEN"),
            'testMode' => true,
        );
    }


    public function completePurchase(array $parameters = array())
    {
        return $this->createRequest('\\Internal\\Message\\CompletePaymentRequest', $parameters);
    }
}
