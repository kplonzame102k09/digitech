<?php

namespace App\Rules;

use App\Support\FileSignature;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Validates an uploaded file by checking its content signature (magic bytes)
 * so uploads keep working on hosts without the PHP fileinfo extension.
 */
class TrustedUploadFile implements ValidationRule
{
    /**
     * @param  array<int, string>  $allowedExtensions Lower-cased extensions the upload may be.
     */
    public function __construct(protected array $allowedExtensions)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('The :attribute must be a valid uploaded file.');

            return;
        }

        $extension = FileSignature::detectFile((string) $value->getRealPath());

        if ($extension === null) {
            $fail('The :attribute must be a file of type: '.implode(', ', $this->allowedExtensions).'.');

            return;
        }

        if (! in_array($extension, $this->allowedExtensions, true)) {
            $fail('The :attribute must be a file of type: '.implode(', ', $this->allowedExtensions).'.');
        }
    }
}