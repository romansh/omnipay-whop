<?php

namespace Omnipay\Whop\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\AbstractRequest as BaseAbstractRequest;

/**
 * Whop Abstract Request
 *
 * Shared credentials, endpoints and the JSON transport for the Whop REST API.
 */
abstract class AbstractRequest extends BaseAbstractRequest
{
    /**
     * @var string API Production base URL
     */
    protected string $liveEndpoint = 'https://api.whop.com/api/v1';

    /**
     * @var string API Sandbox base URL
     */
    protected string $testEndpoint = 'https://sandbox-api.whop.com/api/v1';

    /**
     * @var string Hosted checkout origin in production
     */
    protected string $liveCheckoutHost = 'https://whop.com';

    /**
     * @var string Hosted checkout origin in the sandbox
     */
    protected string $testCheckoutHost = 'https://sandbox.whop.com';

    /**
     * Get the API Key (Account API key or App API key).
     *
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        return $this->getParameter('apiKey');
    }

    /**
     * Set the API Key.
     *
     * @param string|null $value
     * @return $this
     */
    public function setApiKey(?string $value): self
    {
        return $this->setParameter('apiKey', $value);
    }

    /**
     * Get the Whop Plan ID (plan_xxx) sold by the checkout.
     *
     * @return string|null
     */
    public function getPlanId(): ?string
    {
        return $this->getParameter('planId');
    }

    /**
     * Set the Whop Plan ID.
     *
     * @param string|null $value
     * @return $this
     */
    public function setPlanId(?string $value): self
    {
        return $this->setParameter('planId', $value);
    }

    /**
     * Get the Whop Company ID (biz_xxx), used for inline plans.
     *
     * @return string|null
     */
    public function getCompanyId(): ?string
    {
        return $this->getParameter('companyId');
    }

    /**
     * Set the Whop Company ID.
     *
     * @param string|null $value
     * @return $this
     */
    public function setCompanyId(?string $value): self
    {
        return $this->setParameter('companyId', $value);
    }

    /**
     * Get the Whop Product ID (prod_xxx), used for inline plans.
     *
     * @return string|null
     */
    public function getProductId(): ?string
    {
        return $this->getParameter('productId');
    }

    /**
     * Set the Whop Product ID.
     *
     * @param string|null $value
     * @return $this
     */
    public function setProductId(?string $value): self
    {
        return $this->setParameter('productId', $value);
    }

    /**
     * Get the API base URL for the current mode.
     *
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->getTestMode() ? $this->testEndpoint : $this->liveEndpoint;
    }

    /**
     * Get the origin that relative hosted checkout URLs resolve against.
     *
     * @return string
     */
    public function getCheckoutHost(): string
    {
        return $this->getTestMode() ? $this->testCheckoutHost : $this->liveCheckoutHost;
    }

    /**
     * Validate that parameters are present and not empty.
     *
     * Omnipay's validate() only checks isset(), so the gateway's '' defaults
     * would pass as configured.
     *
     * @param string ...$keys
     * @throws InvalidRequestException
     * @return void
     */
    protected function validateFilled(string ...$keys): void
    {
        foreach ($keys as $key) {
            $value = $this->getParameter($key);

            if ($value === null || (is_string($value) && trim($value) === '')) {
                throw new InvalidRequestException("The $key parameter is required");
            }
        }
    }

    /**
     * Send an authenticated request to the Whop API and decode the JSON response.
     *
     * Error responses keep Whop's {"error": {...}} body. A non-JSON error body
     * (for example a gateway timeout page) is replaced by a synthetic error.
     *
     * @param string $method
     * @param string $path Path below the API base URL, starting with "/"
     * @param array<string, mixed>|null $payload JSON body, or null for no body
     * @return array<string, mixed>
     */
    protected function sendApiRequest(string $method, string $path, ?array $payload = null): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->getApiKey(),
            'Accept'        => 'application/json',
        ];
        $body = null;

        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
            // An empty payload must still be a JSON object, not "[]".
            $body = json_encode($payload === [] ? new \stdClass() : $payload, JSON_UNESCAPED_SLASHES);
        }

        $httpResponse = $this->httpClient->request($method, $this->getEndpoint().$path, $headers, $body);
        $status = $httpResponse->getStatusCode();
        $data = json_decode((string) $httpResponse->getBody(), true);

        if (!is_array($data)) {
            $data = [];
        }

        if ($status >= 400 && !isset($data['error'])) {
            $data['error'] = [
                'type'    => 'http_error',
                'message' => "Whop API responded with HTTP {$status}.",
            ];
        }

        return $data;
    }
}
