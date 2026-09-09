<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isStudent() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'programType' => ['required', 'string', 'max:255'],
            'gradeLevel' => ['required', 'string', 'max:255'],
            'strand' => ['required', 'string', 'max:255'],
            'track' => ['nullable', 'string', 'max:255'],
            'schoolYear' => ['required', 'string', 'max:255'],
            'trainingLevel' => ['nullable', 'string', 'max:255'],
            'contact' => ['required', 'string', 'max:20'],
            'birthDate' => ['required', 'date'],
            'address' => ['required', 'string', 'max:255'],
            'guardianName' => ['required', 'string', 'max:255'],
            'guardianContact' => ['required', 'string', 'max:20'],
        ];
    }
}
