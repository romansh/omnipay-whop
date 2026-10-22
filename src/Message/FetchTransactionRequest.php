<?php

namespace Omnipay\Whop\Message;

/**
 * Whop Fetch Transaction Request
 *
 * Retrieves a Payment by its ID ('transactionReference', pay_xxx). Useful to
 * reconcile state after a missed or out-of-order webhook.
 *
 * @link https://docs.whop.com/api-reference/payments/retrieve-payment
 */
class FetchTransactionRequest extends AbstractRequest
{
    /**
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validateFilled('apiKey', 'transactionReference');

        return [];
    }

    /**
     * @param mixed $data
     * @return FetchTransactionResponse
     */
    public function sendData($data): FetchTransactionResponse
    {
        return $this->response = new FetchTransactionResponse(
            $this,
            $this->sendApiRequest('GET', '/payments/'.rawurlencode((string) $this->getTransactionReference()))
        );
    }
}
