<?php

namespace Omnipay\Whop\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;

/**
 * Whop Purchase Response
 *
 * Wraps the created checkout configuration and exposes its hosted checkout URL.
 */
class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    /**
     * A created checkout is not a completed payment; the buyer still has to pay.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return false;
    }

    /**
     * Does the response require a redirect to the hosted checkout?
     *
     * @return bool
     */
    public function isRedirect(): bool
    {
        return !isset($this->data['error']) && $this->getRedirectUrl() !== null;
    }

    /**
     * Get the absolute hosted checkout URL.
     *
     * Whop documents purchase_url as a path such as "/checkout/ch_xxx/"; a
     * relative value is resolved against whop.com (or sandbox.whop.com).
     *
     * @return string|null
     */
    public function getRedirectUrl(): ?string
    {
        $url = $this->data['purchase_url'] ?? null;

        if (!is_string($url) || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        if (parse_url($url, PHP_URL_SCHEME) !== null) {
            return $url;
        }

        $host = $this->request instanceof AbstractRequest
            ? $this->request->getCheckoutHost()
            : 'https://whop.com';

        return $host.'/'.ltrim($url, '/');
    }

    /**
     * Get the redirect method.
     *
     * @return string
     */
    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    /**
     * Get the redirect data (none required for GET redirects).
     *
     * @return array<string, mixed>
     */
    public function getRedirectData(): array
    {
        return [];
    }

    /**
     * Get the checkout configuration ID (ch_xxx).
     *
     * @return string|null
     */
    public function getTransactionReference(): ?string
    {
        return isset($this->data['id']) ? (string) $this->data['id'] : null;
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
     * Get the error code (or error type) from the response.
     *
     * @return string|null
     */
    public function getCode(): ?string
    {
        $code = $this->data['error']['code'] ?? $this->data['error']['type'] ?? null;

        return $code !== null ? (string) $code : null;
    }
}
