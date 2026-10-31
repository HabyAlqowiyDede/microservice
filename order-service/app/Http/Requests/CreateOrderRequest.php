<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
        ];
    }
    public function messages(): array
    {
        return [
            'user_id.required' => 'Field user_id wajib diisi',
            'user_id.integer' => 'Field user_id harus berupa angka bulat',
            'user_id.min' => 'Field user_id harus lebih besar dari 0',
        ];
    }
}
