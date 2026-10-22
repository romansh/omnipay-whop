<?php

namespace Omnipay\Whop\Tests\Support;

use Nyholm\Psr7\Response;
use Omnipay\Common\Http\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Omnipay HTTP client that records requests and replays queued responses.
 */
final class RecordingHttpClient implements ClientInterface
{
    /** @var list<array{method: string, uri: string, headers: array<string, string>, body: mixed}> */
    public array $requests = [];

    /** @var list<ResponseInterface> */
    private array $responses = [];

    /**
     * @param array<string, mixed> $body
     */
    public function queueJson(array $body, int $status = 200): self
    {
        $this->responses[] = new Response($status, ['Content-Type' => 'application/json'], json_encode($body));

        return $this;
    }

    public function queueRaw(string $body, int $status): self
    {
        $this->responses[] = new Response($status, [], $body);

        return $this;
    }

    public function request($method, $uri, array $headers = [], $body = null, $protocolVersion = '1.1'): ResponseInterface
    {
        $this->requests[] = ['method' => $method, 'uri' => (string) $uri, 'headers' => $headers, 'body' => $body];

        if ($this->responses === []) {
            throw new \LogicException("Unexpected HTTP request: {$method} {$uri}");
        }

        return array_shift($this->responses);
    }

    /**
     * @return array<string, mixed>
     */
    public function lastJsonBody(): array
    {
        $body = end($this->requests)['body'] ?? null;

        return json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
    }
}
