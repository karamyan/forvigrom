<?php

namespace Omnipay\Arca;

use Omnipay\Common\AbstractGateway;

class Gateway extends AbstractGateway
{
    public function getName()
    {
        return 'Arca';
    }

    public function getDefaultParameters()
    {
        return array(
            'token' => "",
            'testMode' => true,
        );
    }


    public function checkCard(array $parameters = array())
    {
        return $this->createRequest('\\Omnipay\\Arca\\Message\\CheckCardRequest', $parameters);
    }
    public function completePurchase(array $parameters = array())
    {
        return $this->createRequest('\\Omnipay\\Arca\\Message\\CompletePaymentRequest', $parameters);
    }
}
