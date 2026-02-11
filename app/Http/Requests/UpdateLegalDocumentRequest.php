<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLegalDocumentRequest extends FormRequest
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
            'id'            => 'required|exists:legal_documents,id',
            'document_id'   => 'sometimes|string|unique:legal_documents,document_id,' . $this->id,
            'document_name' => 'sometimes|string|max:255',
            'type'          => 'sometimes|string|max:255',
            'expiry_date'   => 'sometimes|date|after:today',
            'file'          => 'sometimes|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ];
    }
} 