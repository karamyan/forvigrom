<?php

namespace Omnipay\Internal\Message;

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

    public function getMessage()
    {
        return $this->data['message'];
    }

    public function getJson()
    {
        $response = new \stdClass();
        $response->code = $this->getCode();
        $response->message = $this->getmessage();

        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}
