<?php

namespace Omnipay\Easypay\Message;

use Omnipay\Common\Message\AbstractResponse;

class CheckUserWithdrawResponse extends AbstractResponse
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

    public function getDebt()
    {
        return $this->data['Debt'];
    }

    public function getResponseMessage()
    {
        return $this->data['ResponseMessage'];
    }

    public function getJson()
    {
        $response = new \stdClass();
        $response->ResponseMessage = $this->getResponseMessage();
        $response->ResponseCode = $this->getResponseCode();
        $response->Debt = $this->getDebt();
        $response->Checksum = $this->getChecksum();
        $response->PropertyList = $this->getPropertyList();


        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}
