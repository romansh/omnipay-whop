<?php

namespace Omnipay\Whop\Message;

use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Whop\WebhookSignature;

/**
 * Whop Complete Purchase Request
 *
 * Handles an incoming Whop webhook: verifies the Standard Webhooks signature
 * (webhook-id, webhook-timestamp, webhook-signature headers) against the raw
 * body, rejects stale deliveries, and decodes the event envelope.
 *
 * @link https://docs.whop.com/developer/guides/webhooks
 */
class CompletePurchaseRequest extends AbstractRequest
{
    /**
     * Internal cache for the verified and decoded event.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $data = null;

    /**
     * Get the webhook signing secret (ws_xxx).
     *
     * @return string|null
     */
    public function getWebhookSecret(): ?string
    {
        return $this->getParameter('webhookSecret');
    }

    /**
     * Set the webhook signing secret, exactly as Whop issued it.
     *
     * @param string|null $value
     * @return $this
     */
    public function setWebhookSecret(?string $value): self
    {
        return $this->setParameter('webhookSecret', $value);
    }

    /**
     * Get the accepted webhook age in seconds.
     *
     * @return int
     */
    public function getWebhookTolerance(): int
    {
        $tolerance = $this->getParameter('webhookTolerance');

        return is_numeric($tolerance) ? (int) $tolerance : WebhookSignature::DEFAULT_TOLERANCE;
    }

    /**
     * Set the accepted webhook age in seconds (default 300).
     *
     * @param int|null $value
     * @return $this
     */
    public function setWebhookTolerance(?int $value): self
    {
        return $this->setParameter('webhookTolerance', $value);
    }

    /**
     * Verify the delivery and return the decoded event envelope.
     *
     * The result is cached so the signature is checked only once.
     *
     * @throws InvalidResponseException If the secret, headers, signature, timestamp or JSON is invalid.
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $secret = (string) $this->getWebhookSecret();
        if ($secret === '') {
            throw new InvalidResponseException('Webhook secret is not configured. Please set webhookSecret parameter.');
        }

        $headers = $this->httpRequest->headers;
        $id = (string) $headers->get('webhook-id', '');
        $timestamp = (string) $headers->get('webhook-timestamp', '');
        $signature = (string) $headers->get('webhook-signature', '');

        if ($id === '' || $timestamp === '' || $signature === '') {
            throw new InvalidResponseException(
                'Webhook signature headers (webhook-id, webhook-timestamp, webhook-signature) are missing'
            );
        }

        $payload = (string) $this->httpRequest->getContent();

        if (!WebhookSignature::matches($secret, $id, $timestamp, $payload, $signature)) {
            throw new InvalidResponseException('Invalid webhook signature. The signature does not match the expected value.');
        }

        if (!WebhookSignature::isFresh($timestamp, $this->getWebhookTolerance())) {
            throw new InvalidResponseException('Webhook timestamp is outside the tolerance window.');
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            throw new InvalidResponseException('Invalid webhook payload: not valid JSON');
        }

        return $this->data = $data;
    }

    /**
     * Wrap the verified event in a response.
     *
     * @param mixed $data The data returned from getData()
     * @return CompletePurchaseResponse
     */
    public function sendData($data): CompletePurchaseResponse
    {
        return $this->response = new CompletePurchaseResponse($this, $data);
    }
}
