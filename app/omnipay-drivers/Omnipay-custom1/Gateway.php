<?php

namespace Omnipay\Custom;

use Omnipay\Common\AbstractGateway;

class Gateway extends AbstractGateway
{
    public function getName()
    {
        return 'Custom';
    }

    public function getDefaultParameters()
    {
        return array(
            'apiKey' => '',
            'secret' => '',
            'testMode' => false,
        );
    }

    /**
     * Create a new charge.
     *
     * @param  array $parameters request parameters
     *
     * @return Message\PaymentResponse               response
     */
    public function purchase(array $parameters = [])
    {
        return $this->createRequest('Omnipay\Custom\Message\PaymentRequest', $parameters);
    }

    /**
     * Finalises a payment (callback).
     *
     * @param  array $parameters request parameters
     *
     * @return Message\PaymentResponse               response
     */
    public function completePurchase(array $parameters = array())
    {
        return $this->createRequest('\\Omnipay\\Custom\\Message\\CompletePurchaseRequest', $parameters);
    }
}
