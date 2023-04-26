<?php

namespace Omnipay\Custom\Message;

use Omnipay\Common\Message\AbstractResponse;

class StatusCallback extends AbstractResponse
{
    /**
     * This status is sent when the customer tries to pay via Credit Card or Direct Debit
     * but our provider declines the transaction. If the merchant doesn't accept Credit
     * Card or Direct Debit payments via Skrill then you will never receive the failed
     * status.
     */
    public const STATUS_FAILED = -2;

    /**
     * This status is sent when the transaction is processed and the funds have been
     * received on the merchant's Skrill account.
     */
    public const STATUS_PROCESSED = 2;

    /**
     * Construct a StatusCallback with the respective POST data.
     *
     * @param array $post post data
     */
    public function __construct(array $post)
    {
        $this->data = $post;
    }

    /**
     * Is the response successful?
     *
     * @return boolean
     */
    public function isSuccessful()
    {
        if (!$this->testMdSignatures()) {
            return false;
        }

        return in_array($this->getStatus(), [self::STATUS_PENDING, self::STATUS_PROCESSED]);
    }
    /**
     * Get the status of the transaction.
     *
     * * -3 - Chargeback (see STATUS_CHARGEBACK)
     * * -2 - Failed (see STATUS_FAILED)
     * * -1 - Cancelled (see STATUS_CANCELLED)
     * * 0 - Pending (see STATUS_PENDING)
     * * 2 - Processed (see STATUS_PROCESSED)
     *
     * @return int status
     */
    public function getStatus()
    {
        return (int)$this->data['status'];
    }

    /**
     * Get the unique reference or identification number provided by the merchant.
     *
     * @return string transaction id
     */
    public function getTransactionId()
    {
        return $this->data['transaction_id'] ?: $this->getTransactionReference();
    }

    /**
     * Get the URL to which the transaction details will be posted after the payment
     * process is complete.
     *
     * @return string notify url
     */
    public function getNotifyUrl()
    {
        return isset($this->data['notifyUrl']) ? $this->data['notifyUrl'] : null;
    }
}
