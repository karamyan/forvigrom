<?php

namespace Omnipay\Telcell\Message;

use Omnipay\Common\Message\AbstractResponse;

class CompletePaymentResponse extends AbstractResponse
{
    public function isSuccessful()
    {
        return (bool)$this->data['is_successful'];
    }

    public function getCode()
    {
        return $this->data['code'];
    }

    public function getXml()
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
        $xml->code = $this->getCode();
        return $xml->asXML();
    }
}
