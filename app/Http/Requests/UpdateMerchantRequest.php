<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMerchantRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $merchantId = $this->id;


        return [
            'id' => 'required|exists:users,id',
            'country_id' => 'sometimes|exists:countries,id',
            'user_id' => 'sometimes|exists:users,id',
            'address' => 'sometimes|string|max:255',
            'contact_no' => 'sometimes|phone:AUTO',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            // 'currency' => 'sometimes|string|max:10',
        ];
    }

    public function messages()
    {
        return [
            'contact_no.phone' => 'The phone number must be a valid international phone number.',
        ];
    }
}
