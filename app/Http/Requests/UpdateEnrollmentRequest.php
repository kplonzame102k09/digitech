<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEnrollmentRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'in:Draft,Submitted'],
            'programType' => ['nullable', 'string', 'max:255'],
            'gradeLevel' => ['nullable', 'string', 'max:255'],
            'strand' => ['nullable', 'string', 'max:255'],
            'track' => ['nullable', 'string', 'max:255'],
            'schoolYear' => ['nullable', 'string', 'max:255'],
            'trainingLevel' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:20'],
            'birthDate' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'guardianName' => ['nullable', 'string', 'max:255'],
            'guardianContact' => ['nullable', 'string', 'max:20'],
        ];
    }
}
