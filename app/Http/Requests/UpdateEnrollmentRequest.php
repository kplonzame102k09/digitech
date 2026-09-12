<?php

namespace App\Http\Requests;

use App\Services\PortalDataService;
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
        $svc = app(PortalDataService::class);
        $programType = trim((string) ($this->input('programType') ?? ''));
        $isTvet = $programType === 'TVET';
        $isAmbiguous = $programType === '';

        $programs = $svc->catalogue('strands');
        $qualifications = $svc->catalogue('tvetQualifications');

        // When the request doesn't say whether this is TVET or Senior High we
        // accept any offered program or qualification name for the track so a
        // plain profile edit never gets a false rejection.
        $trackAllowed = $isTvet
            ? $qualifications
            : ($isAmbiguous
                ? array_values(array_unique(array_merge($programs, $qualifications)))
                : $programs);

        return [
            'status' => ['nullable', 'string', 'in:Draft,Submitted'],
            'programType' => ['nullable', 'string', 'in:Senior High,TVET'],
            'gradeLevel' => ['nullable', 'string', 'max:255'],
            'strand' => ['nullable', 'string', 'max:255', $this->allowedCatalogue($programs, false)],
            'track' => ['nullable', 'string', 'max:255', $this->allowedCatalogue($trackAllowed, false)],
            'schoolYear' => ['nullable', 'string', 'max:255'],
            'trainingLevel' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($svc, $isTvet): void {
                if ($value === null || $value === '' || ! $isTvet) {
                    return;
                }

                $track = (string) ($this->input('track') ?? '');
                if ($track !== '') {
                    $levels = $svc->tvetLevelsForQualification($track);
                    if ($levels !== [] && ! in_array((string) $value, $levels, true)) {
                        $fail('The selected training level is not offered by this qualification.');
                    }
                }
            }],
            'contact' => ['nullable', 'string', 'max:20'],
            'birthDate' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'guardianName' => ['nullable', 'string', 'max:255'],
            'guardianContact' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Closure rule that only accepts values the registrar offers.
     * Both fields are optional, so empty values are tolerated.
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
