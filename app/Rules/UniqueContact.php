<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A phone number (users.contact) may only belong to one account across every
 * role. The import placeholder ("00000000000") and blank values count as "no
 * phone" and are exempt, so roster imports with missing numbers keep working.
 */
class UniqueContact implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreId = null)
    {
    }

    /**
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $contact = is_string($value) ? trim($value) : '';

        if ($contact === '' || $contact === '00000000000') {
            return;
        }

        $query = User::query()->where('contact', $contact);

        if ($this->ignoreId !== null) {
            $query->where('id', '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail('This phone number is already registered to another account.');
        }
    }
}