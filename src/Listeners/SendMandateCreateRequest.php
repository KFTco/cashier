<?php

namespace Vandar\Cashier\Listeners;

use Carbon\Carbon;
use Vandar\Cashier\Client\CasingFormatter;
use Vandar\Cashier\Client\Client;
use Vandar\Cashier\Events\MandateCreating;
use Vandar\Cashier\Exceptions\InvalidPayloadException;
use Vandar\Cashier\Vandar;

class SendMandateCreateRequest
{
    public function handle(MandateCreating $event)
    {
        $payload = $event->mandate->only(['bank_code', 'mobile_number', 'count', 'limit', 'name', 'email', 'expiration_date', 'wage_type']);
        $payload['mobile_number'] = $payload['mobile_number'] ?? $event->mandate->user->mobile_number;
        $payload['callback_url'] = config('vandar.mandate_callback_url');
        $payload['expiration_date'] = Carbon::parse($payload['expiration_date'])->format('Y-m-d') ?? date('Y-m-d', strtotime(date('Y-m-d') . ' + 3 years'));

        $payload = CasingFormatter::mobileKeyFormat($payload);

        $response = Client::request('post', Vandar::url('MANDATE_API', 'store'), $payload, true);

        $event->mandate->token = $response->json()['result']['authorization']['token'];
        $event->mandate->expiration_date = $event->mandate->expiration_date ?? $payload['expiration_date'];
    }
}
