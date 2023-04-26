<?php
/**
 * Custom driver for Omnipay PHP payment library.
 *
 * @link      https://github.com/hiqdev/omnipay-custom
 * @package   omnipay-custom
 * @license   MIT
 * @copyright Copyright (c) 2019
 */

namespace Omnipay\Custom;

use Illuminate\Config\Repository;
use Omnipay\Common\Http\ClientInterface;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\Custom\Message\CompletePurchaseRequest;
use Omnipay\Custom\Message\PurchaseRequest;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

/**
 * Braintree Gateway
 * @method \Omnipay\Common\Message\RequestInterface authorize(array $options = array())
 * @method \Omnipay\Common\Message\RequestInterface completeAuthorize(array $options = array())
 * @method \Omnipay\Common\Message\RequestInterface capture(array $options = array())
 * @method \Omnipay\Common\Message\RequestInterface refund(array $options = array())
 * @method \Omnipay\Common\Message\RequestInterface void(array $options = array())
 * @method \Omnipay\Common\Message\RequestInterface createCard(array $options = array())
 * @method \Omnipay\Common\Message\RequestInterface updateCard(array $options = array())
 * @method \Omnipay\Common\Message\RequestInterface deleteCard(array $options = array())
 */
class Gateway extends AbstractGateway
{
    /**
     * Get name
     * @return string
     */
    public function getName()
    {
        return 'Custom';
    }

    /**
     * Gateway constructor.
     *
     * @param ClientInterface|null $httpClient
     * @param HttpRequest|null $httpRequest
     */
    public function __construct(ClientInterface $httpClient = null, HttpRequest $httpRequest = null)
    {
        parent::__construct($httpClient, $httpRequest);
    }

    /**
     * Get default parameters
     * @return array|Repository|mixed
     */
    public function getDefaultParameters()
    {
        return [
            'transactionId' => '',
            'key' => '',
        ];
    }



    /**
     * Sets the request  key.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setKey($value)
    {
        return $this->setParameter('key', $value);
    }

    /**
     * Get the request secret key.
     * @return mixed
     */
    public function getKey()
    {
        return $this->getParameter('key');
    }

    /**
     * Sets the request Transaction Id.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setTransactionId($value)
    {
        return $this->setParameter('transactionId', $value);
    }

    /**
     * Get the request Transaction Id.
     * @return $this
     */
    public function getTransactionId()
    {
        return $this->getParameter('transactionId');
    }
    /**
     * Set custom data to get back as is
     *
     * @param array $value
     *
     * @return $this
     */
    public function setCustomData(array $value)
    {
        return $this->setParameter('customData', $value);
    }

    /**
     * Get custom data
     * @return mixed
     */
    public function getCustomData()
    {
        return $this->getParameter('customData', []);
    }

    /**
     * Create a purchase request
     *
     * @param array $options
     *
     * @return AbstractRequest|RequestInterface
     */
    public function purchase(array $options = array())
    {
        return $this->createRequest(PurchaseRequest::class, $options);
    }

    /**
     * Complete purchase
     *
     * @param array $options
     *
     * @return AbstractRequest|RequestInterface
     */
    public function completePurchase(array $options = array())
    {
        return $this->createRequest(CompletePurchaseRequest::class, $options);
    }

    public function __call($name, $arguments)
    {
        // TODO: Implement @method \Omnipay\Common\Message\RequestInterface authorize(array $options = array())
        // TODO: Implement @method \Omnipay\Common\Message\RequestInterface completeAuthorize(array $options = array())
        // TODO: Implement @method \Omnipay\Common\Message\RequestInterface capture(array $options = array())
        // TODO: Implement @method \Omnipay\Common\Message\RequestInterface refund(array $options = array())
        // TODO: Implement @method \Omnipay\Common\Message\RequestInterface void(array $options = array())
        // TODO: Implement @method \Omnipay\Common\Message\RequestInterface createCard(array $options = array())
        // TODO: Implement @method \Omnipay\Common\Message\RequestInterface updateCard(array $options = array())
        // TODO: Implement @method \Omnipay\Common\Message\RequestInterface deleteCard(array $options = array())
    }
}
