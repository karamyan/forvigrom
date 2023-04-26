<?php

namespace Omnipay\Custom\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;

class PaymentResponse extends AbstractResponse implements RedirectResponseInterface
{
    /**
     * Gateway endpoint
     * @var string
     */
    protected $endpoint = 'http://10.10.10.128:3030/deposit';
    /**
     * @return false
     */
    public function isSuccessful()
    {
        return false;
    }

    public function getUrl()
    {
        return $this->data['url'];
    }
    public function getJson()
    {
        $response = new \stdClass();
        $response->url = $this->getUrl();

        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}
