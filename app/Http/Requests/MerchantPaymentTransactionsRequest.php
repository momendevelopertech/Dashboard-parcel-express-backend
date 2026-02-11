<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MerchantPaymentTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'   => 'sometimes|in:cod,invoice',
            'status' => 'sometimes|in:pending,settled,paid,failed',
            'from'   => 'sometimes|date',
            'to'     => 'sometimes|date|after_or_equal:from',
            'page'   => 'sometimes|integer|min:1',
        ];
    }
} 