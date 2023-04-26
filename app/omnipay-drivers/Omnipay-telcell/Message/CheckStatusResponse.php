<?php

namespace Omnipay\Telcell\Message;

use Omnipay\Common\Message\AbstractResponse;

class CheckStatusResponse extends AbstractResponse
{
    public function isSuccessful()
    {
        return (bool)$this->data['is_successful'];
    }

    public function getCode()
    {
        return $this->data['code'];
    }

    public function getId()
    {
        return $this->data['id'];
    }

    public function getDate()
    {
        return $this->data['date'];
    }
    public function getXml()
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
        if ($this->getId()) {
            $xml->authcode = $this->getId();
        }
        if ($this->getDate()) {
            $xml->date = $this->getDate();
        }
        $xml->code = $this->getCode();
        return $xml->asXML();
    }
}
