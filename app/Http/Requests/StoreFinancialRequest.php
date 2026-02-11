<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\User;

class StoreFinancialRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Adjust based on your authorization logic
    }

    public function rules()
    {
    $payeeIds = $this->input('payee_ids', []);

        // Ensure it's always an array
        $payeeIds = is_array($payeeIds) ? $payeeIds : [$payeeIds];
      
        $total = totalFinancialRequest(
            ids: $payeeIds,
            type: $this->input('type')
        );

        return [
            'type' => 'required|in:merchant_settlement,driver_salary,branch_settlement,other',
            'financial_proof' => 'required_if:type,branch_settlement',
            // 'payee_ids' => [
            //     'required_if:type,driver_salary,merchant_settlement',
            //     function ($attribute, $value, $fail) {
            //         if (!is_array($value) || empty($value)) {
            //             $fail('The payee_ids field must be a non-empty array when type is driver_salary.');
            //         } else {
            //             $invalidIds = array_filter($value, fn($id) => !User::where('id', $id)->exists());
            //             if (!empty($invalidIds)) {
            //                 $fail('The following payee_ids are invalid: ' . implode(', ', $invalidIds));
            //             }
            //         }
            //     },
            // ],
            'period_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0|max:' . $total,
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
