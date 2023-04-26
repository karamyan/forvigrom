<?php

namespace Omnipay\Easypay\Message;

use Omnipay\Common\Message\AbstractRequest;

class CheckUserRequest extends AbstractRequest
{
    public function setResponseCode($responseCode)
    {
        $this->setParameter("responseCode", $responseCode);
    }

    public function getResponseCode()
    {
        return $this->getParameter("responseCode");
    }

    public function getChecksum()
    {
        return $this->getParameter("checksum");
    }

    public function setChecksum($checksum)
    {
        return $this->setParameter("checksum", $checksum);
    }

    public function getPropertyList()
    {
        return $this->getParameter("propertyList");
    }

    public function setPropertyList($propertyList)
    {
        return $this->setParameter("propertyList", $propertyList);
    }

    public function getDebt()
    {
        return $this->getParameter("debt");
    }

    public function setDebt($propertyList)
    {
        return $this->setParameter("debt", $propertyList);
    }

    public function setResponseMessage($ResponseMessage)
    {
        $this->setParameter("ResponseMessage", $ResponseMessage);
    }

    public function getResponseMessage()
    {
        return $this->getParameter("ResponseMessage");
    }


    public function getData()
    {
        $data =  [
            "ResponseCode"=>$this->getResponseCode(),
            "ResponseMessage"=>$this->getResponseMessage(),
//            "is_successful"=>$this->getIsSuccessful(),
            "PropertyList"=>$this->getPropertyList(),
            "Checksum"=>$this->getChecksum(),
            "Debt"=>$this->getDebt(),
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
