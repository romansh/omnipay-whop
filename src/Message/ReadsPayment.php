<?php

namespace Omnipay\Whop\Message;

use Money\Currencies\ISOCurrencies;
use Money\Currency;

/**
 * Accessors for a Whop Payment object (pay_xxx).
 *
 * Whop sends amounts as decimal numbers in the payment currency (49.9 for
 * $49.90); the *Integer accessors convert them to minor units.
 *
 * @link https://docs.whop.com/api-reference/payments/payment
 */
trait ReadsPayment
{
    /**
     * The Whop Payment object this response describes, if any.
     *
     * @return array<string, mixed>|null
     */
    abstract public function getPayment(): ?array;

    /**
     * Get the Whop Payment ID (pay_xxx).
     *
     * @return string|null
     */
    public function getTransactionReference(): ?string
    {
        return $this->paymentString('id');
    }

    /**
     * Get the transactionId passed to purchase(), read back from the payment metadata.
     *
     * @return string|null
     */
    public function getTransactionId(): ?string
    {
        $value = $this->getMetadata()[PurchaseRequest::TRANSACTION_ID_METADATA_KEY] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * Get the payment metadata (inherited from the checkout configuration).
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        $metadata = $this->getPayment()['metadata'] ?? null;

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * Get the checkout configuration ID (ch_xxx) that produced the payment.
     *
     * @return string|null
     */
    public function getCheckoutReference(): ?string
    {
        return $this->paymentString('checkout_configuration_id');
    }

    /**
     * Get the Whop Plan ID (plan_xxx) that was purchased.
     *
     * @return string|null
     */
    public function getPlanId(): ?string
    {
        return $this->paymentString('plan', 'id');
    }

    /**
     * Get the Whop Product ID (prod_xxx) that was purchased.
     *
     * @return string|null
     */
    public function getProductId(): ?string
    {
        return $this->paymentString('product', 'id');
    }

    /**
     * Get the Whop Membership ID (mem_xxx) the payment belongs to.
     *
     * @return string|null
     */
    public function getMembershipId(): ?string
    {
        return $this->paymentString('membership', 'id');
    }

    /**
     * Get the buyer (Whop user: id, email, name, username).
     *
     * @return array<string, mixed>|null
     */
    public function getCustomer(): ?array
    {
        $user = $this->getPayment()['user'] ?? null;

        return is_array($user) ? $user : null;
    }

    /**
     * Get the buyer's Whop user ID (user_xxx).
     *
     * @return string|null
     */
    public function getCustomerReference(): ?string
    {
        return $this->paymentString('user', 'id');
    }

    /**
     * Get the payment status (draft, open, paid, pending, void, ...).
     *
     * @return string|null
     */
    public function getPaymentStatus(): ?string
    {
        return $this->paymentString('status');
    }

    /**
     * Get the friendly payment substatus (succeeded, failed, refunded, ...).
     *
     * @return string|null
     */
    public function getPaymentSubstatus(): ?string
    {
        return $this->paymentString('substatus');
    }

    /**
     * Get the ISO currency code of the payment, uppercased.
     *
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        $currency = $this->paymentString('currency');

        return $currency !== null ? strtoupper($currency) : null;
    }

    /**
     * Get the payment total as a decimal string ("49.90").
     *
     * @return string|null
     */
    public function getAmount(): ?string
    {
        $total = $this->getPayment()['total'] ?? null;

        if (!is_numeric($total)) {
            return null;
        }

        return number_format((float) $total, $this->currencyDecimals($this->getCurrency()), '.', '');
    }

    /**
     * Get the payment total in minor units (4990 for $49.90).
     *
     * @return int|null
     */
    public function getAmountInteger(): ?int
    {
        return $this->toMinorUnits($this->getPayment()['total'] ?? null, $this->getCurrency());
    }

    /**
     * Get the payment subtotal in minor units.
     *
     * @return int|null
     */
    public function getSubtotalInteger(): ?int
    {
        return $this->toMinorUnits($this->getPayment()['subtotal'] ?? null, $this->getCurrency());
    }

    /**
     * Get the cumulative refunded amount of the payment in minor units.
     *
     * @return int|null
     */
    public function getRefundedAmountInteger(): ?int
    {
        return $this->toMinorUnits($this->getPayment()['refunded_amount'] ?? null, $this->getCurrency());
    }

    /**
     * Convert a Whop decimal amount to minor units of the given currency.
     *
     * @param mixed $amount
     * @param string|null $currency
     * @return int|null
     */
    protected function toMinorUnits($amount, ?string $currency): ?int
    {
        if (!is_numeric($amount)) {
            return null;
        }

        return (int) round((float) $amount * (10 ** $this->currencyDecimals($currency)));
    }

    /**
     * Number of decimal places of an ISO currency; 2 for unknown codes.
     *
     * @param string|null $currency
     * @return int
     */
    protected function currencyDecimals(?string $currency): int
    {
        if ($currency === null || $currency === '') {
            return 2;
        }

        try {
            return (new ISOCurrencies())->subunitFor(new Currency(strtoupper($currency)));
        } catch (\Throwable $exception) {
            return 2;
        }
    }

    /**
     * Read a scalar value from the payment, optionally from a nested object.
     *
     * @param string $key
     * @param string|null $child
     * @return string|null
     */
    private function paymentString(string $key, ?string $child = null): ?string
    {
        $value = $this->getPayment()[$key] ?? null;

        if ($child !== null) {
            $value = is_array($value) ? ($value[$child] ?? null) : null;
        }

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
