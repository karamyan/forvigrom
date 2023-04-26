<?php

namespace Omnipay\Telcell\Message;

use Omnipay\Common\Message\AbstractRequest;

class CheckStatusRequest extends AbstractRequest
{
    public function setCode($number)
    {
        $this->setParameter("code", $number);
    }

    public function getCode()
    {
        return $this->getParameter("code");
    }

    public function setDate($number)
    {
        $this->setParameter("date", $number);
    }

    public function getDate()
    {
        return $this->getParameter("date");
    }
    public function setId($number)
    {
        $this->setParameter("id", $number);
    }

    public function getId()
    {
        return $this->getParameter("id");
    }

    public function getData()
    {
        $data =  [
            "code"=>$this->getCode(),
            "date"=>$this->getDate(),
            "id"=>$this->getId(),
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
     * @return CheckStatusResponse
     */
    protected function createResponse($data)
    {
        return $this->response = new CheckStatusResponse($this, $data);
    }
}
