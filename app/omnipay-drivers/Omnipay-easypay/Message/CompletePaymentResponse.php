<?php

namespace Omnipay\Easypay\Message;

use Omnipay\Common\Message\AbstractResponse;

class CompletePaymentResponse extends AbstractResponse
{
    public function isSuccessful()
    {
        return (bool)$this->data['is_successful'];
    }

    public function getResponseCode()
    {
        return $this->data['ResponseCode'];
    }

    public function getChecksum()
    {
        return $this->data['Checksum'];
    }

    public function getPropertyList()
    {
        return $this->data['PropertyList'];
    }

    public function getDtTime()
    {
        return $this->data['DtTime'];
    }

    public function getResponseMessage()
    {
        return $this->data['ResponseMessage'];
    }

    public function getJson()
    {
        $response = new \stdClass();
        $response->ResponseCode = $this->getResponseCode();
        $response->ResponseMessage = $this->getResponseMessage();
        $response->PropertyList = $this->getPropertyList();
        $response->Checksum = $this->getChecksum();
        $response->DtTime = $this->getDtTime();

        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}
