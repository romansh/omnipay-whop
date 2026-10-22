<?php

namespace Omnipay\Whop\Tests\Message;

use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Whop\Gateway;
use Omnipay\Whop\Message\CompletePurchaseResponse;
use Omnipay\Whop\Tests\Support\RecordingHttpClient;
use Omnipay\Whop\WebhookSignature;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class CompletePurchaseRequestTest extends TestCase
{
    private const SECRET = 'ws_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function test_signature_matches_an_independent_reference_implementation(): void
    {
        // Computed with Python's hmac over the literal secret bytes, as Whop documents.
        $payload = '{"id":"msg_bQPHmO2eBnHYtWWuxAN9K3Xd","type":"payment.succeeded","data":{"id":"pay_vector"}}';

        $this->assertSame(
            'v1,BG3wPhnjZ5C1sIg58zPVyFdM2XECchYg2CXWGyDWTZQ=',
            WebhookSignature::sign(self::SECRET, 'msg_bQPHmO2eBnHYtWWuxAN9K3Xd', '1786381404', $payload)
        );
    }

    public function test_payment_succeeded_event_is_verified_and_parsed(): void
    {
        $response = $this->complete($this->signedRequest([
            'id' => 'msg_1',
            'type' => 'payment.succeeded',
            'timestamp' => '2026-10-22T10:00:00.000Z',
            'data' => [
                'id' => 'pay_1',
                'status' => 'paid',
                'substatus' => 'succeeded',
                'total' => 49.9,
                'subtotal' => 49.9,
                'currency' => 'usd',
                'checkout_configuration_id' => 'ch_1',
                'metadata' => ['transaction_id' => 'ORDER-1', 'payment_id' => '42'],
                'plan' => ['id' => 'plan_abc'],
                'product' => ['id' => 'prod_abc'],
                'membership' => ['id' => 'mem_1', 'status' => 'active'],
                'user' => ['id' => 'user_1', 'email' => 'buyer@example.test'],
            ],
        ]));

        $this->assertInstanceOf(CompletePurchaseResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertSame('payment.succeeded', $response->getEventType());
        $this->assertSame('payment.succeeded', $response->getCode());
        $this->assertSame('msg_1', $response->getEventId());
        $this->assertSame('2026-10-22T10:00:00.000Z', $response->getEventTimestamp());
        $this->assertSame('pay_1', $response->getTransactionReference());
        $this->assertSame('ORDER-1', $response->getTransactionId());
        $this->assertSame('42', $response->getMetadata()['payment_id']);
        $this->assertSame('ch_1', $response->getCheckoutReference());
        $this->assertSame('plan_abc', $response->getPlanId());
        $this->assertSame('prod_abc', $response->getProductId());
        $this->assertSame('mem_1', $response->getMembershipId());
        $this->assertSame('user_1', $response->getCustomerReference());
        $this->assertSame('buyer@example.test', $response->getCustomer()['email']);
        $this->assertSame('USD', $response->getCurrency());
        $this->assertSame(4990, $response->getAmountInteger());
        $this->assertSame(4990, $response->getSubtotalInteger());
        $this->assertNull($response->getRefundReference());
        $this->assertNull($response->getDisputeReference());
    }

    public function test_failed_payment_event_exposes_the_failure(): void
    {
        $response = $this->complete($this->signedRequest([
            'id' => 'msg_2',
            'type' => 'payment.failed',
            'data' => ['id' => 'pay_2', 'status' => 'open', 'failure_message' => 'Card declined'],
        ]));

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isFailed());
        $this->assertSame('Card declined', $response->getMessage());
    }

    public function test_refund_events_expose_the_refund_and_its_payment(): void
    {
        $response = $this->complete($this->signedRequest([
            'id' => 'msg_3',
            'type' => 'refund.updated',
            'data' => [
                'id' => 'rf_1',
                'amount' => 10,
                'currency' => 'usd',
                'status' => 'succeeded',
                'payment' => [
                    'id' => 'pay_1',
                    'total' => 49.9,
                    'currency' => 'usd',
                    'metadata' => ['transaction_id' => 'ORDER-1'],
                ],
            ],
        ]));

        $this->assertTrue($response->isRefunded());
        $this->assertFalse($response->isSuccessful());
        $this->assertSame('rf_1', $response->getRefundReference());
        $this->assertSame('succeeded', $response->getRefundStatus());
        $this->assertSame('USD', $response->getRefundCurrency());
        $this->assertSame(1000, $response->getRefundAmountInteger());
        $this->assertSame('pay_1', $response->getTransactionReference());
        $this->assertSame('ORDER-1', $response->getTransactionId());
        $this->assertSame(4990, $response->getAmountInteger());
    }

    public function test_a_pending_refund_is_not_reported_as_refunded(): void
    {
        $response = $this->complete($this->signedRequest([
            'id' => 'msg_4',
            'type' => 'refund.created',
            'data' => ['id' => 'rf_2', 'amount' => 10, 'currency' => 'usd', 'status' => 'pending', 'payment' => ['id' => 'pay_1']],
        ]));

        $this->assertFalse($response->isRefunded());
        $this->assertSame('pending', $response->getRefundStatus());
    }

    public function test_dispute_events_expose_the_dispute_and_its_payment(): void
    {
        $response = $this->complete($this->signedRequest([
            'id' => 'msg_5',
            'type' => 'dispute.created',
            'data' => ['id' => 'dspt_1', 'status' => 'needs_response', 'payment' => ['id' => 'pay_1']],
        ]));

        $this->assertTrue($response->isDisputed());
        $this->assertSame('dspt_1', $response->getDisputeReference());
        $this->assertSame('needs_response', $response->getDisputeStatus());
        $this->assertSame('pay_1', $response->getTransactionReference());
    }

    public function test_any_of_several_signatures_may_match(): void
    {
        $request = $this->signedRequest(['id' => 'msg_6', 'type' => 'payment.pending', 'data' => ['id' => 'pay_6']]);
        $request->headers->set(
            'webhook-signature',
            'v1,'.base64_encode(str_repeat('x', 32)).' '.$request->headers->get('webhook-signature')
        );

        $this->assertTrue($this->complete($request)->isPending());
    }

    public function test_an_invalid_signature_is_rejected(): void
    {
        $request = $this->signedRequest(['id' => 'msg_7', 'type' => 'payment.succeeded', 'data' => []]);
        $request->headers->set('webhook-signature', 'v1,'.base64_encode(str_repeat('0', 32)));

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        $this->complete($request);
    }

    public function test_a_tampered_body_is_rejected(): void
    {
        $signed = $this->signedRequest(['id' => 'msg_8', 'type' => 'payment.succeeded', 'data' => ['total' => 1]]);
        $tampered = $this->request(
            '{"id":"msg_8","type":"payment.succeeded","data":{"total":1000}}',
            $signed->headers->all()
        );

        $this->expectException(InvalidResponseException::class);

        $this->complete($tampered);
    }

    public function test_a_stale_delivery_is_rejected(): void
    {
        $request = $this->signedRequest(
            ['id' => 'msg_9', 'type' => 'payment.succeeded', 'data' => []],
            timestamp: time() - WebhookSignature::DEFAULT_TOLERANCE - 1
        );

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('tolerance');

        $this->complete($request);
    }

    public function test_the_tolerance_can_be_widened(): void
    {
        $request = $this->signedRequest(
            ['id' => 'msg_10', 'type' => 'payment.succeeded', 'data' => []],
            timestamp: time() - 3600
        );

        $this->assertTrue($this->complete($request, ['webhookTolerance' => 7200])->isSuccessful());
    }

    public function test_missing_headers_are_rejected(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('headers');

        $this->complete($this->request('{}', []));
    }

    public function test_a_missing_secret_is_rejected(): void
    {
        $gateway = new Gateway(new RecordingHttpClient(), $this->signedRequest(['type' => 'payment.succeeded']));

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('secret');

        $gateway->completePurchase()->send();
    }

    public function test_a_valid_signature_over_a_non_json_body_is_rejected(): void
    {
        $timestamp = (string) time();
        $request = $this->request('not json', [
            'webhook-id' => 'msg_11',
            'webhook-timestamp' => $timestamp,
            'webhook-signature' => WebhookSignature::sign(self::SECRET, 'msg_11', $timestamp, 'not json'),
        ]);

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('JSON');

        $this->complete($request);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function complete(Request $request, array $options = []): CompletePurchaseResponse
    {
        $gateway = new Gateway(new RecordingHttpClient(), $request);
        $gateway->setWebhookSecret(self::SECRET);

        return $gateway->completePurchase($options)->send();
    }

    /**
     * @param array<string, mixed> $event
     */
    private function signedRequest(array $event, ?int $timestamp = null): Request
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $id = (string) ($event['id'] ?? 'msg_default');
        $timestamp = (string) ($timestamp ?? time());

        return $this->request($payload, [
            'webhook-id' => $id,
            'webhook-timestamp' => $timestamp,
            'webhook-signature' => WebhookSignature::sign(self::SECRET, $id, $timestamp, $payload),
        ]);
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function request(string $payload, array $headers): Request
    {
        $request = Request::create('/webhooks/whop', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }
}
