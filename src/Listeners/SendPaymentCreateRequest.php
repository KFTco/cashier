<?php

namespace Vandar\Cashier\Listeners;

use Illuminate\Validation\ValidationException;
use Vandar\Cashier\Client\CasingFormatter;
use Vandar\Cashier\Client\Client;
use Vandar\Cashier\Events\PaymentCreating;
use Vandar\Cashier\Models\Payment;
use Vandar\Cashier\Vandar;

class SendPaymentCreateRequest
{
    public function handle(PaymentCreating $event)
    {
        $payload = $event->payment->only(['amount', 'mobile_number', 'factor_number', 'description', 'valid_card_number']);
        $payload['api_key'] = config('vandar.api_key');
        $payload['callback_url'] = config('vandar.callback_url');

        $payload = CasingFormatter::convertKeyFormat('camel', $payload, ['factor_number']);

        $response = Client::request('post', Vandar::url('IPG_API', 'send'), $payload, false);

        if((! in_array($response->getStatusCode(), [200, 201])) || $response->json()['status'] !== 1)
        {
            $event->payment->status = Payment::STATUS_FAILED;
            throw ValidationException::withMessages($response->json()['errors']);

        }
        $event->payment->token = $response->json()['token'];
    }
}
