<?php

namespace Omnipay\Whop;

use Omnipay\Common\AbstractGateway;
use Omnipay\Whop\Message\CompletePurchaseRequest;
use Omnipay\Whop\Message\FetchTransactionRequest;
use Omnipay\Whop\Message\PurchaseRequest;
use Omnipay\Whop\Message\RefundRequest;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Whop Gateway
 *
 * Hosted checkout (checkout configurations), webhook verification, payment
 * lookup and refunds for Whop.
 *
 * @link https://whop.com/
 * @link https://docs.whop.com/developer/guides/accept-payments
 *
 * @method \Omnipay\Common\Message\NotificationInterface acceptNotification(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface authorize(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface completeAuthorize(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface capture(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface void(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface createCard(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface updateCard(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface deleteCard(array $options = [])
 */
class Gateway extends AbstractGateway
{
    /**
     * Get the human-readable name of the gateway.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Whop';
    }

    /**
     * Get the default parameters for the gateway.
     *
     * @return array<string, mixed>
     */
    public function getDefaultParameters(): array
    {
        return [
            'apiKey' => '',
            'webhookSecret' => '',
            'planId' => '',
            'companyId' => '',
            'productId' => '',
            'testMode' => false,
        ];
    }

    /**
     * Get the API Key.
     *
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        return $this->getParameter('apiKey');
    }

    /**
     * Set the API Key (Account API key or App API key).
     *
     * @param string|null $value
     * @return self
     */
    public function setApiKey(?string $value): self
    {
        return $this->setParameter('apiKey', $value);
    }

    /**
     * Get the webhook signing secret.
     *
     * @return string|null
     */
    public function getWebhookSecret(): ?string
    {
        return $this->getParameter('webhookSecret');
    }

    /**
     * Set the webhook signing secret (ws_xxx), exactly as Whop issued it.
     *
     * @param string|null $value
     * @return self
     */
    public function setWebhookSecret(?string $value): self
    {
        return $this->setParameter('webhookSecret', $value);
    }

    /**
     * Get the default Whop Plan ID.
     *
     * @return string|null
     */
    public function getPlanId(): ?string
    {
        return $this->getParameter('planId');
    }

    /**
     * Set the default Whop Plan ID (plan_xxx) sold by purchase().
     *
     * @param string|null $value
     * @return self
     */
    public function setPlanId(?string $value): self
    {
        return $this->setParameter('planId', $value);
    }

    /**
     * Get the Whop Company ID.
     *
     * @return string|null
     */
    public function getCompanyId(): ?string
    {
        return $this->getParameter('companyId');
    }

    /**
     * Set the Whop Company ID (biz_xxx), required for inline plans.
     *
     * @param string|null $value
     * @return self
     */
    public function setCompanyId(?string $value): self
    {
        return $this->setParameter('companyId', $value);
    }

    /**
     * Get the Whop Product ID.
     *
     * @return string|null
     */
    public function getProductId(): ?string
    {
        return $this->getParameter('productId');
    }

    /**
     * Set the Whop Product ID (prod_xxx), required for inline plans.
     *
     * @param string|null $value
     * @return self
     */
    public function setProductId(?string $value): self
    {
        return $this->setParameter('productId', $value);
    }

    /**
     * Replace the internal HTTP Request object.
     * Crucial for webhooks: the signature covers the raw body of this request.
     *
     * @param SymfonyRequest $httpRequest
     * @return self
     */
    public function setHttpRequest(SymfonyRequest $httpRequest): self
    {
        $this->httpRequest = $httpRequest;

        return $this;
    }

    /**
     * Create a hosted checkout session.
     *
     * @param array<string, mixed> $options
     * @return PurchaseRequest
     */
    public function purchase(array $options = []): PurchaseRequest
    {
        return $this->createRequest(PurchaseRequest::class, $options);
    }

    /**
     * Verify and parse an incoming webhook.
     *
     * @param array<string, mixed> $options
     * @return CompletePurchaseRequest
     */
    public function completePurchase(array $options = []): CompletePurchaseRequest
    {
        return $this->createRequest(CompletePurchaseRequest::class, $options);
    }

    /**
     * Retrieve a payment by its ID ('transactionReference').
     *
     * @param array<string, mixed> $options
     * @return FetchTransactionRequest
     */
    public function fetchTransaction(array $options = []): FetchTransactionRequest
    {
        return $this->createRequest(FetchTransactionRequest::class, $options);
    }

    /**
     * Refund a payment fully, or partially when 'amount' is given.
     *
     * @param array<string, mixed> $options
     * @return RefundRequest
     */
    public function refund(array $options = []): RefundRequest
    {
        return $this->createRequest(RefundRequest::class, $options);
    }
}
