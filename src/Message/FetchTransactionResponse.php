<?php

namespace Omnipay\Whop\Message;

use Omnipay\Common\Message\AbstractResponse;

/**
 * Whop Fetch Transaction Response
 *
 * Wraps a retrieved Payment object.
 */
class FetchTransactionResponse extends AbstractResponse
{
    use ReadsPayment;

    /**
     * Was the payment collected?
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return !isset($this->data['error']) && $this->getPaymentStatus() === 'paid';
    }

    /**
     * Is the payment still awaiting collection?
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return !isset($this->data['error'])
            && in_array($this->getPaymentStatus(), ['draft', 'open', 'pending', 'authorized'], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPayment(): ?array
    {
        return isset($this->data['error']) ? null : $this->data;
    }

    /**
     * Get the API error message, or the payment failure message.
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        if (isset($this->data['error'])) {
            return (string) ($this->data['error']['message'] ?? 'Unknown error');
        }

        $message = $this->data['failure_message'] ?? null;

        return is_string($message) && $message !== '' ? $message : null;
    }

    /**
     * Get the API error code (or error type).
     *
     * @return string|null
     */
    public function getCode(): ?string
    {
        $code = $this->data['error']['code'] ?? $this->data['error']['type'] ?? null;

        return $code !== null ? (string) $code : null;
    }
}
