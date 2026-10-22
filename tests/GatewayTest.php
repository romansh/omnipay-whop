<?php

namespace Omnipay\Whop\Tests;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Omnipay;
use Omnipay\Whop\Gateway;
use Omnipay\Whop\Message\PurchaseResponse;
use Omnipay\Whop\Tests\Support\RecordingHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class GatewayTest extends TestCase
{
    private RecordingHttpClient $http;

    private Gateway $gateway;

    protected function setUp(): void
    {
        $this->http = new RecordingHttpClient();
        $this->gateway = new Gateway($this->http, new Request());
        $this->gateway->initialize([
            'apiKey' => 'apik_test',
            'testMode' => true,
        ]);
    }

    public function test_it_is_discoverable_by_the_omnipay_factory(): void
    {
        $gateway = Omnipay::create('Whop', $this->http, new Request());

        $this->assertInstanceOf(Gateway::class, $gateway);
        $this->assertSame('Whop', $gateway->getName());
        $this->assertFalse($gateway->getTestMode());
    }

    public function test_purchase_creates_a_checkout_for_an_existing_plan(): void
    {
        $this->http->queueJson([
            'id' => 'ch_123',
            'purchase_url' => 'https://whop.com/checkout/plan_abc?session=ch_123',
        ]);

        $response = $this->gateway->purchase([
            'planId' => 'plan_abc',
            'transactionId' => 'ORDER-1',
            'returnUrl' => 'https://shop.test/checkout/success?reference=ORDER-1',
            'metadata' => ['payment_id' => '42'],
        ])->send();

        $request = $this->http->requests[0];
        $this->assertSame('POST', $request['method']);
        $this->assertSame('https://sandbox-api.whop.com/api/v1/checkout_configurations', $request['uri']);
        $this->assertSame('Bearer apik_test', $request['headers']['Authorization']);
        $this->assertSame('application/json', $request['headers']['Content-Type']);
        $this->assertSame([
            'mode' => 'payment',
            'plan_id' => 'plan_abc',
            'metadata' => ['payment_id' => '42', 'transaction_id' => 'ORDER-1'],
            'redirect_url' => 'https://shop.test/checkout/success?reference=ORDER-1',
        ], $this->http->lastJsonBody());

        $this->assertInstanceOf(PurchaseResponse::class, $response);
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isRedirect());
        $this->assertSame('GET', $response->getRedirectMethod());
        $this->assertSame('https://whop.com/checkout/plan_abc?session=ch_123', $response->getRedirectUrl());
        $this->assertSame('ch_123', $response->getTransactionReference());
    }

    public function test_the_gateway_plan_is_used_when_the_purchase_names_none(): void
    {
        $this->http->queueJson(['id' => 'ch_1', 'purchase_url' => 'https://whop.com/checkout/ch_1/']);
        $this->gateway->setPlanId('plan_default');

        $this->gateway->purchase(['transactionId' => 'ORDER-2'])->send();

        $this->assertSame('plan_default', $this->http->lastJsonBody()['plan_id']);
    }

    public function test_live_mode_uses_the_production_api(): void
    {
        $this->http->queueJson(['id' => 'ch_1', 'purchase_url' => '/checkout/ch_1/']);
        $this->gateway->setTestMode(false);

        $response = $this->gateway->purchase(['planId' => 'plan_abc', 'transactionId' => 'ORDER-3'])->send();

        $this->assertSame('https://api.whop.com/api/v1/checkout_configurations', $this->http->requests[0]['uri']);
        $this->assertSame('https://whop.com/checkout/ch_1/', $response->getRedirectUrl());
    }

    public function test_a_relative_purchase_url_resolves_against_the_sandbox_in_test_mode(): void
    {
        $this->http->queueJson(['id' => 'ch_9', 'purchase_url' => '/checkout/ch_9/']);

        $response = $this->gateway->purchase(['planId' => 'plan_abc', 'transactionId' => 'ORDER-4'])->send();

        $this->assertSame('https://sandbox.whop.com/checkout/ch_9/', $response->getRedirectUrl());
    }

    public function test_purchase_without_a_plan_creates_an_inline_one_time_plan(): void
    {
        $this->http->queueJson(['id' => 'ch_inline', 'purchase_url' => '/checkout/ch_inline/']);

        $this->gateway->purchase([
            'companyId' => 'biz_123',
            'productId' => 'prod_123',
            'amount' => '49.90',
            'currency' => 'USD',
            'description' => 'Featured launch',
            'transactionId' => 'ORDER-5',
        ])->send();

        $body = $this->http->lastJsonBody();
        $this->assertSame('payment', $body['mode']);
        $this->assertArrayNotHasKey('plan_id', $body);
        $this->assertSame([
            'company_id' => 'biz_123',
            'product_id' => 'prod_123',
            'currency' => 'usd',
            'initial_price' => 49.9,
            'plan_type' => 'one_time',
            'visibility' => 'hidden',
            'title' => 'Featured launch',
        ], $body['plan']);
    }

    public function test_purchase_requires_a_plan_or_the_inline_plan_fields(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('companyId');

        $this->gateway->purchase(['transactionId' => 'ORDER-6', 'amount' => '10.00', 'currency' => 'USD'])->send();
    }

    public function test_purchase_requires_a_transaction_id(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('transactionId');

        $this->gateway->purchase(['planId' => 'plan_abc'])->send();
    }

    public function test_api_errors_are_exposed_without_a_redirect(): void
    {
        $this->http->queueJson([
            'error' => ['type' => 'invalid_request_error', 'message' => 'Plan not found', 'code' => 'not_found'],
        ], 404);

        $response = $this->gateway->purchase(['planId' => 'plan_missing', 'transactionId' => 'ORDER-7'])->send();

        $this->assertFalse($response->isRedirect());
        $this->assertSame('Plan not found', $response->getMessage());
        $this->assertSame('not_found', $response->getCode());
    }

    public function test_a_non_json_error_body_becomes_an_http_error(): void
    {
        $this->http->queueRaw('<html>Bad gateway</html>', 502);

        $response = $this->gateway->purchase(['planId' => 'plan_abc', 'transactionId' => 'ORDER-8'])->send();

        $this->assertFalse($response->isRedirect());
        $this->assertSame('Whop API responded with HTTP 502.', $response->getMessage());
        $this->assertSame('http_error', $response->getCode());
    }

    public function test_fetch_transaction_reads_a_payment(): void
    {
        $this->http->queueJson([
            'id' => 'pay_1',
            'status' => 'paid',
            'substatus' => 'succeeded',
            'total' => 49.9,
            'subtotal' => 49.9,
            'currency' => 'usd',
            'checkout_configuration_id' => 'ch_1',
            'metadata' => ['transaction_id' => 'ORDER-9'],
            'plan' => ['id' => 'plan_abc'],
            'user' => ['id' => 'user_1', 'email' => 'buyer@example.test'],
        ]);

        $response = $this->gateway->fetchTransaction(['transactionReference' => 'pay_1'])->send();

        $this->assertSame('GET', $this->http->requests[0]['method']);
        $this->assertSame('https://sandbox-api.whop.com/api/v1/payments/pay_1', $this->http->requests[0]['uri']);
        $this->assertNull($this->http->requests[0]['body']);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('pay_1', $response->getTransactionReference());
        $this->assertSame('ORDER-9', $response->getTransactionId());
        $this->assertSame('ch_1', $response->getCheckoutReference());
        $this->assertSame('plan_abc', $response->getPlanId());
        $this->assertSame('user_1', $response->getCustomerReference());
        $this->assertSame('USD', $response->getCurrency());
        $this->assertSame('49.90', $response->getAmount());
        $this->assertSame(4990, $response->getAmountInteger());
    }

    public function test_full_refund_sends_an_empty_object(): void
    {
        $this->http->queueJson(['id' => 'pay_1', 'currency' => 'usd', 'refunded_amount' => 49.9]);

        $response = $this->gateway->refund(['transactionReference' => 'pay_1'])->send();

        $this->assertSame('https://sandbox-api.whop.com/api/v1/payments/pay_1/refund', $this->http->requests[0]['uri']);
        $this->assertSame('{}', $this->http->requests[0]['body']);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame(4990, $response->getRefundedAmountInteger());
    }

    public function test_partial_refund_sends_the_amount(): void
    {
        $this->http->queueJson(['id' => 'pay_1', 'currency' => 'usd', 'refunded_amount' => 5]);

        $this->gateway->refund(['transactionReference' => 'pay_1', 'amount' => '5.00', 'currency' => 'USD'])->send();

        $this->assertEquals(['partial_amount' => 5], $this->http->lastJsonBody());
    }

    public function test_requests_need_an_api_key(): void
    {
        $this->gateway->setApiKey('');

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('apiKey');

        $this->gateway->fetchTransaction(['transactionReference' => 'pay_1'])->send();
    }

    public function test_zero_decimal_currencies_convert_to_whole_minor_units(): void
    {
        $this->http->queueJson(['id' => 'pay_jpy', 'status' => 'paid', 'total' => 1500, 'currency' => 'jpy']);

        $response = $this->gateway->fetchTransaction(['transactionReference' => 'pay_jpy'])->send();

        $this->assertSame('1500', $response->getAmount());
        $this->assertSame(1500, $response->getAmountInteger());
    }
}
