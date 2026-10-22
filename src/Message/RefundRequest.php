<?php

namespace Omnipay\Whop\Message;

/**
 * Whop Refund Request
 *
 * Refunds a Payment ('transactionReference', pay_xxx). Without 'amount' the
 * full payment is refunded; with 'amount' a partial refund of that amount in
 * the payment currency is issued. Requires the payment:manage permission.
 *
 * @link https://docs.whop.com/developer/guides/refunds-and-disputes
 */
class RefundRequest extends AbstractRequest
{
    /**
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validateFilled('apiKey', 'transactionReference');

        $data = [];

        if ($this->getAmount() !== null) {
            $data['partial_amount'] = (float) $this->getAmount();
        }

        return $data;
    }

    /**
     * @param mixed $data
     * @return RefundResponse
     */
    public function sendData($data): RefundResponse
    {
        $path = '/payments/'.rawurlencode((string) $this->getTransactionReference()).'/refund';

        return $this->response = new RefundResponse($this, $this->sendApiRequest('POST', $path, $data));
    }
}
