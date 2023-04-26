<?php

namespace Omnipay\Telcell\Message;

use Omnipay\Common\Message\AbstractRequest;

class CheckUserRequest extends AbstractRequest
{
    public function setCode($number)
    {
        $this->setParameter("code", $number);
    }

    public function getCode()
    {
        return $this->getParameter("code");
    }

    public function setMessage($message)
    {
        $this->setParameter("message", $message);
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
        return $this->response = new CheckUserResponse($this, $data);
    }
}
