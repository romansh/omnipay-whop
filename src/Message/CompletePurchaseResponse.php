<?php

namespace Omnipay\Whop\Message;

use Omnipay\Common\Message\AbstractResponse;

/**
 * Whop Complete Purchase Response
 *
 * A verified webhook event. The envelope is
 * {"id": "msg_xxx", "type": "payment.succeeded", "timestamp": "...", "data": {...}}.
 * For payment.* events "data" is the Payment; for refund.* and dispute.* events
 * it is the Refund or Dispute, which carries the Payment under "payment".
 * Payment accessors (transaction reference, metadata, amounts) read that Payment
 * for every event type.
 */
class CompletePurchaseResponse extends AbstractResponse
{
    use ReadsPayment;

    /**
     * Was the payment collected?
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->getEventType() === 'payment.succeeded';
    }

    /**
     * Is the payment still in progress (created, pending, authorized or awaiting 3DS)?
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return in_array($this->getEventType(), [
            'payment.created',
            'payment.pending',
            'payment.authorized',
            'payment.requires_action',
        ], true);
    }

    /**
     * Did the payment fail?
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->getEventType() === 'payment.failed';
    }

    /**
     * Was the payment canceled?
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->getEventType() === 'payment.canceled';
    }

    /**
     * Is this a refund event whose refund has succeeded?
     *
     * @return bool
     */
    public function isRefunded(): bool
    {
        return $this->isRefundEvent() && $this->getRefundStatus() === 'succeeded';
    }

    /**
     * Is this a dispute (chargeback) event?
     *
     * @return bool
     */
    public function isDisputed(): bool
    {
        return str_starts_with((string) $this->getEventType(), 'dispute.');
    }

    /**
     * Get the event type, e.g. "payment.succeeded".
     *
     * @return string|null
     */
    public function getEventType(): ?string
    {
        return isset($this->data['type']) ? (string) $this->data['type'] : null;
    }

    /**
     * Get the event (message) ID, e.g. "msg_xxx". Retries keep the same ID.
     *
     * @return string|null
     */
    public function getEventId(): ?string
    {
        return isset($this->data['id']) ? (string) $this->data['id'] : null;
    }

    /**
     * Get the event time as sent by Whop (ISO 8601).
     *
     * @return string|null
     */
    public function getEventTimestamp(): ?string
    {
        return isset($this->data['timestamp']) ? (string) $this->data['timestamp'] : null;
    }

    /**
     * Get the event object (Payment, Refund, Dispute, Membership, ...).
     *
     * @return array<string, mixed>
     */
    public function getObject(): array
    {
        return isset($this->data['data']) && is_array($this->data['data']) ? $this->data['data'] : [];
    }

    /**
     * Get the Payment the event is about.
     *
     * @return array<string, mixed>|null
     */
    public function getPayment(): ?array
    {
        $object = $this->getObject();

        if (str_starts_with((string) $this->getEventType(), 'payment.')) {
            return $object;
        }

        return isset($object['payment']) && is_array($object['payment']) ? $object['payment'] : null;
    }

    /**
     * Get the refund ID (rf_xxx) for refund events.
     *
     * @return string|null
     */
    public function getRefundReference(): ?string
    {
        return $this->isRefundEvent() ? $this->objectString('id') : null;
    }

    /**
     * Get the refund status (pending, requires_action, succeeded, failed, canceled).
     *
     * @return string|null
     */
    public function getRefundStatus(): ?string
    {
        return $this->isRefundEvent() ? $this->objectString('status') : null;
    }

    /**
     * Get the ISO currency code of the refund, uppercased.
     *
     * @return string|null
     */
    public function getRefundCurrency(): ?string
    {
        $currency = $this->isRefundEvent() ? $this->objectString('currency') : null;

        return $currency !== null ? strtoupper($currency) : null;
    }

    /**
     * Get the refund amount in minor units.
     *
     * @return int|null
     */
    public function getRefundAmountInteger(): ?int
    {
        return $this->isRefundEvent()
            ? $this->toMinorUnits($this->getObject()['amount'] ?? null, $this->getRefundCurrency())
            : null;
    }

    /**
     * Get the dispute ID (dspt_xxx) for dispute events.
     *
     * @return string|null
     */
    public function getDisputeReference(): ?string
    {
        return $this->isDisputed() ? $this->objectString('id') : null;
    }

    /**
     * Get the dispute status (needs_response, under_review, won, lost, ...).
     *
     * @return string|null
     */
    public function getDisputeStatus(): ?string
    {
        return $this->isDisputed() ? $this->objectString('status') : null;
    }

    /**
     * Get the payment failure message, if Whop sent one.
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        $message = $this->getPayment()['failure_message'] ?? null;

        return is_string($message) && $message !== '' ? $message : null;
    }

    /**
     * Get the code for the event (the event type).
     *
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->getEventType();
    }

    /**
     * Is this a refund.* event?
     *
     * @return bool
     */
    private function isRefundEvent(): bool
    {
        return str_starts_with((string) $this->getEventType(), 'refund.');
    }

    /**
     * Read a scalar value from the event object.
     *
     * @param string $key
     * @return string|null
     */
    private function objectString(string $key): ?string
    {
        $value = $this->getObject()[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
