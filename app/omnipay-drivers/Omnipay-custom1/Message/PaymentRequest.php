<?php

namespace Omnipay\Custom\Message;

use DateTime;
use Omnipay\Common\Message\AbstractRequest;

class PaymentRequest extends AbstractRequest
{
    public function getUrl()
    {
        return $this->getParameter('url');
    }
    public function setUrl($value)
    {
        $this->setParameter('url', $value);
    }

    /**
     * Get the list of payment method codes, indicating the payment methods to be
     * presented to the customer.
     *
     * @return array payment methods
     */
    public function getPaymentMethods()
    {
        return $this->getParameter('paymentMethods');
    }

    /**
     * Set the list of payment method codes, indicating the payment methods to be
     * presented to the customer.
     *
     * @param  array $value payment methods
     *
     * @return self
     */
    public function setPaymentMethods(array $value)
    {
        return $this->setParameter('paymentMethods', $value);
    }

    /**
     * Set a payment method code, indicating the payment method to be presented to the
     * customer.
     *
     * Warning: this resets any previously set payment methods.
     *
     * @param  string $value payment method
     *
     * @return self
     */
    public function setPaymentMethod($value)
    {
        return $this->setPaymentMethods([$value]);
    }

    /**
     * Get the data for this request.
     *
     * @return array request data
     */
    public function getData()
    {
        // merchant details
        $data['transaction_id'] = $this->getTransactionId();
        $data['url'] = $this->getUrl();

        return $data;
    }
    /**
     * @param  array $data payment data to send
     *
     * @return PaymentResponse         payment response
     */
    public function sendData($data)
    {
        return $this->createResponse($data);
    }

    /**
     * @param array $data
     *
     * @return CheckUserResponse
     */
    protected function createResponse($data)
    {
        return $this->response = new PaymentResponse($this, $data);
    }




    /**
     * Get the endpoint for this request.
     *
     * @return string endpoint
     */
    public function getEndpoint()
    {
        return 'http://10.10.10.128:3030';
    }
}
