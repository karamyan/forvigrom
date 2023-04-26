<?php

namespace Omnipay\Custom\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Class PurchaseRequest
 * @package Omnipay\Custom\Message
 */
class PurchaseRequest extends AbstractRequest
{
    /**
     * Sets the request Transaction Id.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setTransactionId($value)
    {
        return $this->setParameter('transactionId', $value);
    }

    /**
     * Get the request Transaction Id.
     * @return $this
     */
    public function getTransactionId()
    {
        return $this->getParameter('transactionId');
    }

    /**
     * Set custom data to get back as is
     *
     * @param array $value
     *
     * @return $this
     */
    public function setCustomData(array $value)
    {
        return $this->setParameter('customData', $value);
    }

    /**
     * Get custom data
     * @return mixed
     */
    public function getCustomData()
    {
        return $this->getParameter('customData', []) ?? [];
    }

    /**
     * Prepare data to send
     * @return array
     * @throws InvalidRequestException
     */
    public function getData()
    {
        $this->validate('key');

        return array_merge($this->getCustomData(), [
            'key'     => $this->getkey(),
            'callBackUrl'     => $this->getCallBackUrl(),
            'transactionId'     => $this->getTransactionId(),
        ]);
    }

    /**
     * Send data and return response instance
     *
     * @param mixed $data
     *
     * @return ResponseInterface|PurchaseResponse
     */
//    public function sendData($data)
//    {
//       $resData =  parent::sendData($data);
//        // TODO: Implement sendData() method.
//        return $this->createReponse($data);
//    }

    public function createResponse($data)
    {
        return $this->response = new PurchaseResponse($this, $data);
    }
}
