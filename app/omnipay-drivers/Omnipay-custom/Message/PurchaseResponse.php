<?php

namespace Omnipay\Custom\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;
use GuzzleHttp\Client;

/**
 * Class PurchaseResponse
 * @package Omnipay\Custom\Message
 */
class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    /**
     * Gateway endpoint
     * @var string
     */
    protected $endpoint = '';

    /**
     * Set successful to false, as transaction is not completed yet
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->data['status']=='success';
    }

    /**
     * Mark purchase as redirect type
     * @return bool
     */
    public function isRedirect()
    {
        return true;
    }

    /**
     * Get redirect URL
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getRedirectUrl()
    {
        return $this->data['url'];
    }

    /**
     * Get redirect method
     * @return string
     */
    public function getRedirectMethod()
    {
        return 'POST';
    }

    /**
     * Get redirect data
     * @return array|mixed
     */
    public function getRedirectData()
    {
        return $this->data;
    }
}
