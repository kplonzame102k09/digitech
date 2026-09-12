<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'firstName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['nullable', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'contact' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,'.$this->user()->id],
            'address' => ['nullable', 'string', 'max:255'],
            'birthDate' => ['nullable', 'date'],
            'birthPlace' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'guardianName' => ['nullable', 'string', 'max:255'],
            'guardianContact' => ['nullable', 'string', 'max:20'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'emergencyContact' => ['nullable', 'string', 'max:120'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'photo' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
