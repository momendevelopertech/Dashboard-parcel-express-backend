<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLegalDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_id'   => 'required|string|unique:legal_documents,document_id',
            'document_name' => 'required|string|max:255',
            'type'          => 'required|string|max:255',
            'expiry_date'   => 'required|date|after:today',
            'file'          => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ];
    }
} 