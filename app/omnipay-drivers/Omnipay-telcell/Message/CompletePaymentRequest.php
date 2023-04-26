<?php

namespace Omnipay\Telcell\Message;

use Omnipay\Common\Message\AbstractRequest;

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

    public function getData()
    {
        $data =  [
            "code"=>$this->getCode(),
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
     * @return CompletePaymentResponse
     */
    protected function createResponse($data)
    {
        return $this->response = new CompletePaymentResponse($this, $data);
    }
}
