# Omnipay-Whop

[Whop](https://whop.com/) gateway driver for the [Omnipay](https://omnipay.thephpleague.com/) payment processing library.

## Installation

```bash
composer require league/omnipay romansh/omnipay-whop
```

`league/omnipay` brings the HTTP client Omnipay needs; any PSR-18 client discovered by `php-http/discovery` works too.

## Features

- **Hosted Checkout** — create a Whop checkout configuration and redirect the buyer to the Whop-hosted page
- **Existing or inline plans** — sell a plan you created in Whop (`planId`), or a one-time price set per order (`amount` + `currency`)
- **Webhook verification** — Standard Webhooks signatures (`webhook-id`, `webhook-timestamp`, `webhook-signature`) with replay protection
- **Payment lookup** — fetch a payment to reconcile state
- **Refunds** — full or partial

## Gateway Parameters

| Parameter | Description | Default |
|-----------------|--------------------------------------------------------------|---------|
| `apiKey` | Whop API key (Account API key or App API key) | `''` |
| `testMode` | Use the sandbox (`sandbox-api.whop.com`, `sandbox.whop.com`) | `false` |
| `webhookSecret` | Webhook signing secret (`ws_...`), exactly as Whop shows it | `''` |
| `planId` | Default Whop Plan ID (`plan_...`) sold by `purchase()` | `''` |
| `companyId` | Whop Company ID (`biz_...`), for inline plans | `''` |
| `productId` | Whop Product ID (`prod_...`), for inline plans | `''` |

The sandbox is a separate Whop environment with its own account, API keys, and webhooks: create them at [sandbox.whop.com](https://sandbox.whop.com).

API key permissions, per Whop's API reference:

- checkout: `checkout_configuration:create`, `checkout_configuration:basic:read`, `plan:create`, `access_pass:create`, `access_pass:update`
- `fetchTransaction()`: `payment:basic:read`
- `refund()`: `payment:manage`

## Usage

### Initialize the gateway

```php
use Omnipay\Omnipay;

$gateway = Omnipay::create('Whop');
$gateway->setApiKey('your_api_key');
$gateway->setTestMode(true);
```

### Create a checkout (purchase)

Sell an existing plan:

```php
$response = $gateway->purchase([
    'planId'        => 'plan_xxxxxxxxxxxxx',             // Whop plan
    'transactionId' => 'ORDER-12345',                    // your unique order ID
    'returnUrl'     => 'https://your-site.com/return',  // where Whop sends the buyer afterwards
    'metadata'      => ['payment_id' => '42'],           // optional, copied onto the payment
])->send();

if ($response->isRedirect()) {
    $response->redirect(); // send the customer to the Whop checkout

    // — or get the URL to use in your own redirect / JS —
    // $checkoutUrl = $response->getRedirectUrl();
    // $checkoutId  = $response->getTransactionReference(); // ch_xxx
} else {
    echo 'Error: ' . $response->getMessage();
}
```

Or charge a price chosen per order. Whop creates a hidden one-time plan for it:

```php
$response = $gateway->purchase([
    'companyId'     => 'biz_xxxxxxxxxxxxxx',
    'productId'     => 'prod_xxxxxxxxxxxxx',
    'amount'        => '49.00',
    'currency'      => 'USD',
    'description'   => 'Premium plan',          // plan title shown at checkout
    'transactionId' => 'ORDER-12345',
    'returnUrl'     => 'https://your-site.com/return',
])->send();
```

`transactionId` is stored in the checkout metadata as `transaction_id`. Whop copies checkout metadata onto the payment, so every webhook can be matched back to your order with `getTransactionId()`.

Whop sends the buyer to `returnUrl` whatever the outcome, so the return page must not grant access by itself. Fulfill orders from the `payment.succeeded` webhook.

### Handle a webhook (completePurchase)

`completePurchase()` reads the incoming HTTP request and verifies the signature over the raw body. It throws `InvalidResponseException` when the secret, the signature headers, the signature or the timestamp is invalid. Deliveries older than 5 minutes are rejected; pass `webhookTolerance` (in seconds) to change that.

```php
$gateway->setWebhookSecret('ws_xxxxxxxx');

$response = $gateway->completePurchase()->send();

switch ($response->getEventType()) {
    case 'payment.succeeded':
        $orderId   = $response->getTransactionId();        // your transactionId
        $paymentId = $response->getTransactionReference(); // pay_xxx
        $amount    = $response->getAmountInteger();        // 4900 (minor units)
        $currency  = $response->getCurrency();             // "USD"
        $planId    = $response->getPlanId();               // plan_xxx
        $customer  = $response->getCustomer();             // ['id' => …, 'email' => …]
        // mark order as paid …
        break;

    case 'payment.failed':
        // $response->getMessage() holds Whop's failure message
        break;

    case 'refund.created':
    case 'refund.updated':
        if ($response->isRefunded()) {                     // refund status "succeeded"
            $refunded = $response->getRefundAmountInteger();
            // process refund for $response->getTransactionId() …
        }
        break;

    case 'dispute.created':
        // $response->getDisputeReference(), $response->getDisputeStatus()
        break;
}

http_response_code(200);
```

Use `$response->getEventId()` (the `webhook-id`, the same on every retry) to drop duplicate deliveries. Respond within 5 seconds and do slow work in a queue.

For `refund.*` and `dispute.*` events, the payment accessors (`getTransactionId()`, `getTransactionReference()`, `getMetadata()`, amounts) read the payment embedded in the refund or dispute.

In a framework, pass the framework's request so the raw body is the one that was signed. With Laravel:

```php
$gateway->setHttpRequest($request); // Illuminate\Http\Request extends the Symfony request
```

### Fetch a payment

```php
$response = $gateway->fetchTransaction(['transactionReference' => 'pay_xxxxxxxxxxxxxx'])->send();

$response->isSuccessful();     // status "paid"
$response->getPaymentStatus(); // draft, open, paid, pending, void, …
$response->getTransactionId(); // your transactionId
```

### Refund

```php
// Full refund
$gateway->refund(['transactionReference' => 'pay_xxxxxxxxxxxxxx'])->send();

// Partial refund
$response = $gateway->refund([
    'transactionReference' => 'pay_xxxxxxxxxxxxxx',
    'amount'               => '5.00',
    'currency'             => 'USD',
])->send();

$response->getRefundedAmountInteger(); // cumulative refunded total
```

The refund settles asynchronously and is reported by `refund.created` / `refund.updated` webhooks.

## Webhook events

| Event | Response |
|---------------------------------------|----------------------------------------------|
| `payment.succeeded` | `isSuccessful()` |
| `payment.created`, `payment.pending`, `payment.authorized`, `payment.requires_action` | `isPending()` |
| `payment.failed` | `isFailed()` |
| `payment.canceled` | `isCancelled()` |
| `refund.created`, `refund.updated` | `isRefunded()` once the refund status is `succeeded` |
| `dispute.created`, `dispute.updated` | `isDisputed()` |

Any other event is still verified and available through `getEventType()` and `getObject()`.

## Testing

```bash
composer install
composer test
```

## Full examples

See [examples/usage.php](examples/usage.php) for a complete standalone script and
a Laravel controller example.

## License

MIT
