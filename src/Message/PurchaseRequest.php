<?php

namespace Omnipay\Whop\Message;

/**
 * Whop Purchase Request
 *
 * Creates a checkout configuration (a hosted checkout session) and returns its
 * purchase URL. The checkout sells either an existing plan ('planId') or a
 * one-time plan created inline from 'amount', 'currency', 'companyId' and
 * 'productId'.
 *
 * 'transactionId' (your order ID) is stored in the checkout metadata under
 * "transaction_id". Whop copies checkout metadata onto the resulting payment,
 * so webhooks can be matched back to the order.
 *
 * @link https://docs.whop.com/api-reference/checkout-configurations/create-checkout-configuration
 */
class PurchaseRequest extends AbstractRequest
{
    /**
     * Metadata key that carries the Omnipay transactionId.
     */
    public const TRANSACTION_ID_METADATA_KEY = 'transaction_id';

    /**
     * Get merchant-defined metadata to include in the checkout.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        $metadata = $this->getParameter('metadata');

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * Set merchant-defined metadata for the checkout.
     *
     * @param array<string, mixed> $value
     * @return $this
     */
    public function setMetadata(array $value): self
    {
        return $this->setParameter('metadata', $value);
    }

    /**
     * Get the affiliate code applied to the checkout.
     *
     * @return string|null
     */
    public function getAffiliateCode(): ?string
    {
        return $this->getParameter('affiliateCode');
    }

    /**
     * Set the affiliate code applied to the checkout.
     *
     * @param string|null $value
     * @return $this
     */
    public function setAffiliateCode(?string $value): self
    {
        return $this->setParameter('affiliateCode', $value);
    }

    /**
     * Prepare the checkout configuration payload.
     *
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validateFilled('apiKey', 'transactionId');

        $data = ['mode' => 'payment'];

        if ($planId = $this->getPlanId()) {
            $data['plan_id'] = $planId;
        } else {
            $this->validateFilled('companyId', 'productId', 'amount', 'currency');

            $plan = [
                'company_id'    => $this->getCompanyId(),
                'product_id'    => $this->getProductId(),
                'currency'      => strtolower((string) $this->getCurrency()),
                'initial_price' => (float) $this->getAmount(),
                'plan_type'     => 'one_time',
                // Inline plans belong to one checkout; keep them off the public product page.
                'visibility'    => 'hidden',
            ];

            if ($description = $this->getDescription()) {
                $plan['title'] = $description;
            }

            $data['plan'] = $plan;
        }

        $data['metadata'] = array_merge($this->getMetadata(), [
            self::TRANSACTION_ID_METADATA_KEY => (string) $this->getTransactionId(),
        ]);

        // Whop sends the buyer here after checkout, whatever the outcome.
        if ($returnUrl = $this->getReturnUrl()) {
            $data['redirect_url'] = $returnUrl;
        }

        if ($affiliateCode = $this->getAffiliateCode()) {
            $data['affiliate_code'] = $affiliateCode;
        }

        return $data;
    }

    /**
     * Create the checkout configuration.
     *
     * @param mixed $data The data returned by getData()
     * @return PurchaseResponse
     */
    public function sendData($data): PurchaseResponse
    {
        return $this->response = new PurchaseResponse(
            $this,
            $this->sendApiRequest('POST', '/checkout_configurations', $data)
        );
    }
}
