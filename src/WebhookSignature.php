<?php

namespace Omnipay\Whop;

/**
 * Whop webhook signatures (Standard Webhooks, v1 scheme).
 *
 * Whop signs "{webhook-id}.{webhook-timestamp}.{raw body}" with HMAC-SHA256 and
 * sends the result as "v1,<base64>" in the webhook-signature header. The HMAC key
 * is the literal bytes of the whole "ws_..." secret: unlike the generic Standard
 * Webhooks convention, the prefix is not stripped and nothing is base64-decoded.
 *
 * @link https://docs.whop.com/developer/guides/webhooks
 */
final class WebhookSignature
{
    /**
     * Maximum age (and clock skew) of a delivery in seconds, as recommended by Whop.
     */
    public const DEFAULT_TOLERANCE = 300;

    /**
     * Compute the "v1,<base64>" signature Whop sends for a delivery.
     *
     * @param string $secret The endpoint's signing secret, exactly as Whop issued it
     * @param string $id The webhook-id header
     * @param int|string $timestamp The webhook-timestamp header (Unix seconds)
     * @param string $payload The raw request body
     * @return string
     */
    public static function sign(string $secret, string $id, int|string $timestamp, string $payload): string
    {
        $digest = hash_hmac('sha256', $id.'.'.$timestamp.'.'.$payload, $secret, true);

        return 'v1,'.base64_encode($digest);
    }

    /**
     * Check whether any signature in the header matches the payload.
     *
     * The header may carry several space-separated signatures (during secret
     * rotation); one valid "v1" entry is enough. The timestamp is not checked here.
     *
     * @param string $secret
     * @param string $id
     * @param string $timestamp
     * @param string $payload
     * @param string $signatureHeader
     * @return bool
     */
    public static function matches(
        string $secret,
        string $id,
        string $timestamp,
        string $payload,
        string $signatureHeader
    ): bool {
        if ($secret === '' || $id === '' || $timestamp === '') {
            return false;
        }

        $expected = self::sign($secret, $id, $timestamp, $payload);

        foreach (preg_split('/\s+/', trim($signatureHeader)) ?: [] as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check that the webhook-timestamp is within the tolerance window (replay protection).
     *
     * @param string $timestamp Unix seconds
     * @param int $tolerance Allowed distance from now, in seconds
     * @param int|null $now Current Unix time; defaults to time()
     * @return bool
     */
    public static function isFresh(string $timestamp, int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): bool
    {
        if (!preg_match('/^\d{1,12}$/', $timestamp)) {
            return false;
        }

        return abs(($now ?? time()) - (int) $timestamp) <= $tolerance;
    }
}
