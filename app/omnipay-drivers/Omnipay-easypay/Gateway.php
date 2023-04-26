<?php

namespace Omnipay\Easypay;

use Omnipay\Common\AbstractGateway;

class Gateway extends AbstractGateway
{
    public function getName()
    {
        return 'Easypay';
    }

    public function getDefaultParameters()
    {
        return array(
            'token' => env("EASYPAY_TOKEN"),
            'testMode' => true,
        );
    }


    public function checkUser(array $parameters = array())
    {
        return $this->createRequest('\\Omnipay\\Easypay\\Message\\CheckUserRequest', $parameters);
    }
    public function completePurchase(array $parameters = array())
    {
        return $this->createRequest('\\Omnipay\\Easypay\\Message\\CompletePaymentRequest', $parameters);
    }
}
