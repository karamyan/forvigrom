<?php

namespace Omnipay\Telcell;

use Omnipay\Common\AbstractGateway;

class Gateway extends AbstractGateway
{
    public function getName()
    {
        return 'telcell';
    }

    public function getDefaultParameters()
    {
        return array(
            'apiKey' => '',
            'secret' => '',
            'testMode' => false,
        );
    }


    public function checkUser(array $parameters = array())
    {
        return $this->createRequest('\\Omnipay\\Telcell\\Message\\CheckUserRequest', $parameters);
    }
    public function completePurchase(array $parameters = array())
    {
        return $this->createRequest('\\Omnipay\\Telcell\\Message\\CompletePaymentRequest', $parameters);
    }
    public function checkStatus(array $parameters = array())
    {
        return $this->createRequest('\\Omnipay\\Telcell\\Message\\CheckStatusRequest', $parameters);
    }
}
