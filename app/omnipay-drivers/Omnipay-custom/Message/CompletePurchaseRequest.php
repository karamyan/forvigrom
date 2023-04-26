<?php

namespace Omnipay\Custom\Message;

use Omnipay\Common\Exception\RuntimeException;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Skrill\Message\PaymentResponse;
use Omnipay\Skrill\Message\StatusCallback;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Class CompletePurchaseRequest
 * @package Omnipay\Custom\Message
 */
class CompletePurchaseRequest extends PurchaseRequest
{
    /**
     * Initialize the object with parameters.
     *
     * If any unknown parameters passed, they will be ignored.
     *
     * @param array $parameters An associative array of parameters
     *
     * @return \Omnipay\Skrill\Message\CompletePurchaseRequest
     * @throws RuntimeException
     */
    public function initialize(array $parameters = [])
    {
        if (null !== $this->response) {
            throw new RuntimeException('Request cannot be modified after it has been sent!');
        }

        $this->parameters = new ParameterBag($parameters);

        return $this;
    }
    /**
    * Get the data for this request.
    *
    * @return array request data
    */
    public function getData()
    {
        return $this->parameters->all();
    }

    /**
     * @param  array $data payment data to send
     *
     * @return PaymentResponse         payment response
     */
    public function sendData($data)
    {
        return $this->response = new CompletePurchaseResponse($data);
    }
}
