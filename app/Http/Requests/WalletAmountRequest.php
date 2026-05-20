<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WalletAmountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /*
             * DECIMAL(15,2) allows 13 digits before the decimal point and 2
             * after it. The regex keeps the API honest before MySQL sees it.
             */
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:9999999999999.99',
                'regex:/^\d{1,13}(\.\d{1,2})?$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.regex' => 'The amount must use at most 13 digits and 2 decimal places.',
        ];
    }
}
