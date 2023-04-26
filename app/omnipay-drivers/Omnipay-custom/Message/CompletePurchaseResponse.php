<?php

namespace Omnipay\Custom\Message;

use Omnipay\Common\Message\AbstractResponse;

/**
 * Class CompletePurchaseResponse
 * @package Omnipay\Custom\Message
 */
class CompletePurchaseResponse extends AbstractResponse
{
    public function __construct(array $post)
    {
        $this->data = $post;
    }
    /**
     * Indicates whether transaction was successful
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->getStatus();
    }

    public function setStatus($status)
    {
        $this->data['status'] = $status;
        return $this;
    }
    public function getStatus()
    {
        return isset($this->data['status']) ? $this->data['status'] : null;
    }
}
