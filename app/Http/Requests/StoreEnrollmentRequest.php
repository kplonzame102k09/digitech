<?php

namespace App\Http\Requests;

use App\Services\PortalDataService;
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
        $svc = app(PortalDataService::class);
        $programType = trim((string) ($this->input('programType') ?? ''));
        $isTvet = $programType === 'TVET';
        $programs = $svc->catalogue('strands');
        $qualifications = $svc->catalogue('tvetQualifications');

        return [
            'status' => ['nullable', 'string', 'in:Draft,Submitted'],
            'programType' => ['required', 'string', 'in:Senior High,TVET'],
            'gradeLevel' => ['required', 'string', 'max:255'],
            'strand' => $isTvet
                ? ['nullable', 'string', 'max:255', $this->allowedCatalogue($programs, false)]
                : ['required', 'string', 'max:255', $this->allowedCatalogue($programs, true)],
            'track' => $isTvet
                ? ['required', 'string', 'max:255', $this->allowedCatalogue($qualifications, true)]
                : ['nullable', 'string', 'max:255', $this->allowedCatalogue($programs, false)],
            'schoolYear' => ['required', 'string', 'max:255'],
            'trainingLevel' => $isTvet
                ? ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($svc): void {
                    $track = (string) ($this->input('track') ?? '');
                    if ($track !== '') {
                        $levels = $svc->tvetLevelsForQualification($track);
                        if ($levels !== [] && ! in_array((string) $value, $levels, true)) {
                            $fail('The selected training level is not offered by this qualification.');
                        }
                    }
                }]
                : ['nullable', 'string', 'max:255'],
            'contact' => ['required', 'string', 'max:20'],
            'birthDate' => ['required', 'date'],
            'address' => ['required', 'string', 'max:255'],
            'guardianName' => ['required', 'string', 'max:255'],
            'guardianContact' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * Closure rule that only accepts values the registrar offers.
     * When $required is false empty/null values pass without checking the list.
     */
    private function allowedCatalogue(array $allowed, bool $required): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($allowed, $required): void {
            if ($value === null || ($value === '' && ! $required)) {
                return;
            }

            if (! in_array((string) $value, $allowed, true)) {
                $field = str_contains($attribute, '.') ? substr($attribute, strrpos($attribute, '.') + 1) : $attribute;
                $fail('The selected '.$field.' is not offered by the college.');
            }
        };
    }
}
