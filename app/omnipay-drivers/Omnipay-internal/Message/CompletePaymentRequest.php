<?php

namespace Omnipay\Internal\Message;

use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Internal\Message\CompletePaymentResponse;

class CompletePaymentRequest extends AbstractRequest
{
    public function setCode($code)
    {
        $this->setParameter("code", $code);
    }

    public function getCode()
    {
        return $this->getParameter("code");
    }

    public function setMessage($message)
    {
        $this->setParameter("code", $message);
    }

    public function getMessage()
    {
        return $this->getParameter("message");
    }

    public function getData()
    {
        $data =  [
            "code"=>$this->getCode(),
            "message"=>$this->getMessage(),
        ];
        return $data;
    }

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
        return $this->response = new CompletePaymentResponse($this, $data);
    }
}
