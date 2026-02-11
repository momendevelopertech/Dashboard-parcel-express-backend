<?php

namespace App\Http\Requests;

use App\Models\Country;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterShipmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    // public function prepareForValidation()
    // {
    //     // Don't add PE prefix for ME (merchant) or DR (driver) waybills
    //     if ($this->tracking_no) {
    //         $prefix = substr($this->tracking_no, 0, 2);
    //         // Only add PE prefix if it doesn't already have PE, ME, or DR prefix
    //         if ($prefix !== "PE" && $prefix !== "ME" && $prefix !== "DR") {
    //             $this->merge([
    //                 'tracking_no' => 'PE' . $this->tracking_no,
    //             ]);
    //         }
    //     }

    //     // Backward compatibility: map 'amount' to 'value' if 'amount' is provided but 'value' is not
    //     if ($this->filled('amount') && !$this->filled('value')) {
    //         $this->merge(['value' => $this->input('amount')]);
    //     }
    // }
    public function prepareForValidation()
    {
        // 🔹 Normalize tracking_no قبل ما نزود أي prefix
        if ($this->has('tracking_no')) {
            $rawTracking = $this->input('tracking_no');

            // لو جاية null أو "null" أو فاضية اعتبرها مش موجودة
            if ($rawTracking === null || $rawTracking === '' || $rawTracking === 'null') {
                $this->merge([
                    'tracking_no' => null,
                ]);
            } else {
                // لو فيه رقم فعلاً، نتأكد من البادئة
                $prefix = substr($rawTracking, 0, 2);

                // ما نزودش PE لو هو أصلاً PE أو ME أو DR
                if (!in_array($prefix, ['PE', 'ME', 'DR'])) {
                    $rawTracking = 'PE' . $rawTracking;
                }

                $this->merge([
                    'tracking_no' => $rawTracking,
                ]);
            }
        }

        // Backward compatibility: map 'amount' to 'value' if 'amount' is provided but 'value' is not
        if ($this->filled('amount') && !$this->filled('value')) {
            $this->merge(['value' => $this->input('amount')]);
        }
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $oman = Country::where('name', 'Oman')->value('id');
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'nullable',
                'max:255',
                function ($attribute, $value, $fail) {
                    // Convert "null" string to actual null
                    if ($value === "null") {
                        $this->merge([$attribute => null]);
                        return;
                    }

                    if (!is_null($value) && $value !== '') {
                        $validator = validator(
                            [$attribute => $value],
                            [$attribute => 'email']
                        );
                        if ($validator->fails()) {
                            $fail('The ' . $attribute . ' must be a valid email address.');
                        }
                    }
                },
            ],
            'tracking_no' => 'nullable',
            'unassigned_shipment_id' => 'nullable|integer|exists:merchant_pickup_shipments,id',
            'cellphone' => 'required|string|max:20',
            'alternatePhone' => 'nullable|string|max:20',
            'district' => 'nullable|string|max:255',
            'country_id' => 'required|integer|exists:countries,id',
            'governorate_id' => [
                Rule::requiredIf($this->input('country_id') == $oman),
            ],
            'value' => [
                Rule::requiredIf($this->input('payment_type') == 'COD'),
                'nullable',
                'numeric',
                'min:0',
            ],
            'state_id' => 'required|integer|exists:states,id',
            'zipcode' => 'nullable|string|max:20',
            'streetAddress' => 'nullable|string|max:255',
            'identify' => 'nullable|string|max:255',
            'taxNumber' => 'nullable|string|max:255',
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => 'nullable|string',
            'is_outsourced' => 'nullable',
            'merchant_id' => 'required|integer|exists:users,id',
            'payment_type' => 'required|string',
            'fee_payer' => 'required|string|in:customer,merchant',
            'item_name' => 'nullable|array',
            'item_name.*' => 'nullable|string|max:255',
            'quantity' => 'nullable|array',
            'quantity.*' => 'nullable|integer|min:1',
            'category' => 'nullable|array',
            'category.*' => 'nullable|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'cellphone.phone' => 'The cellphone number must be a valid Omani phone number.',
            'alternatePhone.phone' => 'The alternate phone number must be a valid Omani phone number.',
        ];
    }
}
