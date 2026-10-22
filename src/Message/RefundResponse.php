<?php

namespace Omnipay\Whop\Message;

use Omnipay\Common\Message\AbstractResponse;

/**
 * Whop Refund Response
 *
 * Whop answers a refund with the updated Payment; refunded_amount is the
 * cumulative refunded total. The refund itself settles asynchronously and is
 * reported through refund.created / refund.updated webhooks.
 */
class RefundResponse extends AbstractResponse
{
    use ReadsPayment;

    /**
     * Was the refund accepted by Whop?
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return !isset($this->data['error']) && isset($this->data['id']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPayment(): ?array
    {
        return isset($this->data['error']) ? null : $this->data;
    }

    /**
     * Get the error message from the response if it exists.
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        if (isset($this->data['error'])) {
            return (string) ($this->data['error']['message'] ?? 'Unknown error');
        }

        return null;
    }

    /**
     * Get the error code (or error type).
     *
     * @return string|null
     */
    public function getCode(): ?string
    {
        $code = $this->data['error']['code'] ?? $this->data['error']['type'] ?? null;

        return $code !== null ? (string) $code : null;
    }
}
