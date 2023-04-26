<?php

namespace Omnipay\Easypay\Message;

use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Easypay\Message\CompletePaymentResponse;

class CompletePaymentRequest extends AbstractRequest
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

    public function setResponseMessage($ResponseMessage)
    {
        $this->setParameter("ResponseMessage", $ResponseMessage);
    }

    public function getResponseMessage()
    {
        return $this->getParameter("ResponseMessage");
    }

    public function setDtTime($DtTime)
    {
        $this->setParameter("DtTime", $DtTime);
    }

    public function getDtTime()
    {
        return $this->getParameter("DtTime");
    }


    public function getData()
    {
        $data =  [
            "ResponseCode"=>$this->getResponseCode(),
            "ResponseMessage"=>$this->getResponseMessage(),
//            "is_successful"=>$this->getIsSuccessful(),
            "PropertyList"=>$this->getPropertyList(),
            "Checksum"=>$this->getChecksum(),
            "DtTime"=>$this->getDtTime(),
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
