<?php

namespace Vandar\VandarCashier\RequestsValidation;

use Illuminate\Foundation\Http\FormRequest;


class SettlementRequestValidation extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }



    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'amount' => 'required|numeric|gte:50000',
            'iban' => 'required|string',
            'notify_url' => 'nullable|string',
            'payment_number	' => 'nullable|numeric',
        ];
    }
}
