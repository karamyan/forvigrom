<?php

namespace Omnipay\Custom\Message;

use GuzzleHttp\Client;
use Omnipay\Common\Message\ResponseInterface;

class AbstractRequest extends \Omnipay\Common\Message\AbstractRequest
{
    protected $endpoint = 'http://10.10.10.128:3030/deposit';
    protected $testednpoint = 'http://10.10.10.128:3030/deposit';


    public function setKey($value)
    {
        return $this->setParameter('key', $value);
    }

    /**
     * Get the request secret key.
     * @return $this
     */
    public function getKey()
    {
        return $this->getParameter('key');
    }

    public function setCallBackUrl($value)
    {
        return $this->setParameter('callBackUrl', $value);
    }

    /**
     * Get the request callback url.
     * @return $this
     */
    public function getCallBackUrl()
    {
        return $this->getParameter('callBackUrl');
    }

    public function getEndpoint()
    {
        if ($this->getTestMode()) {
            return $this->testednpoint;
        }
        return $this->endpoint;
    }
    public function getData()
    {
        return [];
    }
    public function sendData($data)
    {
        $response = $this->httpClient->request('POST', $this->endpoint, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], json_encode($data));

        $result = json_decode($response->getBody()->getContents(), true);

        return $this->createResponse($result);
    }
}
