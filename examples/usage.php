<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Omnipay\Omnipay;

// Create gateway instance
$gateway = Omnipay::create('Whop');

$gateway->setApiKey('your_api_key_here');
$gateway->setTestMode(true); // false for production

// ============================================
// 1. Create payment checkout
// ============================================

try {
    $response = $gateway->purchase([
        'planId' => 'plan_xxxxxxxxxxxxx', // Plan ID from the Whop dashboard
        'transactionId' => 'ORDER-12345', // Your unique order ID
        'returnUrl' => 'https://your-site.com/return', // Where Whop sends the buyer afterwards
        'metadata' => ['payment_id' => '42'], // Copied onto the Whop payment
    ])->send();

    if ($response->isRedirect()) {
        // Redirect to the Whop checkout page
        $response->redirect();

        // Or get redirect URL
        $checkoutUrl = $response->getRedirectUrl();
        echo "Go to checkout: {$checkoutUrl}\n";

        // Save checkout ID for reference
        $checkoutId = $response->getTransactionReference(); // ch_xxx
    } else {
        echo "Error: " . $response->getMessage() . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

// ============================================
// 2. Handle webhook callback
// ============================================

try {
    // Set webhook secret for signature validation (ws_..., as Whop shows it)
    $gateway->setWebhookSecret('your_webhook_secret');

    $response = $gateway->completePurchase()->send();

    switch ($response->getEventType()) {
        case 'payment.succeeded':
            echo "Payment completed!\n";
            echo "Order ID: " . $response->getTransactionId() . "\n";
            echo "Whop payment: " . $response->getTransactionReference() . "\n";
            echo "Amount: " . $response->getAmount() . ' ' . $response->getCurrency() . "\n";

            // Update order status in database
            // updateOrderStatus($response->getTransactionId(), 'paid');
            break;

        case 'payment.failed':
            echo "Payment failed: " . $response->getMessage() . "\n";
            break;

        case 'refund.created':
        case 'refund.updated':
            if ($response->isRefunded()) {
                echo "Refunded " . $response->getRefundAmountInteger() . " minor units\n";
            }
            break;

        case 'dispute.created':
            echo "Dispute opened: " . $response->getDisputeReference() . "\n";
            break;

        default:
            echo "Unhandled event: " . $response->getEventType() . "\n";
    }

    // Acknowledge within 5 seconds, or Whop retries
    http_response_code(200);
} catch (\Exception $e) {
    echo "Error processing webhook: " . $e->getMessage() . "\n";
    http_response_code(400);
}

// ============================================
// 3. Refund a payment
// ============================================

try {
    $response = $gateway->refund([
        'transactionReference' => 'pay_xxxxxxxxxxxxxx',
        'amount' => '5.00', // omit for a full refund
        'currency' => 'USD',
    ])->send();

    echo $response->isSuccessful()
        ? "Refund requested\n"
        : "Error: " . $response->getMessage() . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

// ============================================
// 4. Laravel example
// ============================================

/*
// In config/services.php
'whop' => [
    'api_key' => env('WHOP_API_KEY'),
    'webhook_secret' => env('WHOP_WEBHOOK_SECRET'),
    'plan_id' => env('WHOP_PLAN_ID'),
    'test_mode' => env('WHOP_TEST_MODE', false),
],

// In routes/web.php (exclude the webhook from CSRF verification)
Route::post('/webhooks/whop', [PaymentController::class, 'webhook']);

// In controller
use Illuminate\Http\Request;
use Omnipay\Omnipay;

class PaymentController extends Controller
{
    private $gateway;

    public function __construct()
    {
        $this->gateway = Omnipay::create('Whop');
        $this->gateway->setApiKey(config('services.whop.api_key'));
        $this->gateway->setTestMode(config('services.whop.test_mode'));
    }

    public function create(Request $request)
    {
        $response = $this->gateway->purchase([
            'planId' => config('services.whop.plan_id'),
            'transactionId' => $request->order_id,
            'returnUrl' => route('orders.show', $request->order_id),
        ])->send();

        if ($response->isRedirect()) {
            return redirect($response->getRedirectUrl());
        }

        return back()->withErrors(['payment' => $response->getMessage()]);
    }

    public function webhook(Request $request)
    {
        $this->gateway->setWebhookSecret(config('services.whop.webhook_secret'));
        $this->gateway->setHttpRequest($request);

        try {
            $response = $this->gateway->completePurchase()->send();
        } catch (\Omnipay\Common\Exception\InvalidResponseException $e) {
            return response()->json(['status' => 'invalid_signature'], 401);
        }

        switch ($response->getEventType()) {
            case 'payment.succeeded':
                Order::where('id', $response->getTransactionId())
                    ->update(['status' => 'paid']);
                break;

            case 'refund.updated':
                if ($response->isRefunded()) {
                    Order::where('id', $response->getTransactionId())
                        ->update(['status' => 'refunded']);
                }
                break;
        }

        return response()->json(['status' => 'ok']);
    }
}
*/
