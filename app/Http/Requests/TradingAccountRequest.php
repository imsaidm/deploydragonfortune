<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TradingAccountRequest extends FormRequest
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
        $isRequired = $this->isMethod('POST') ? 'required' : 'nullable';
        return [
            'account_name' => 'required|string|max:255',
            'api_key' => 'required|string',
            'secret_key' => $isRequired . '|string',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
