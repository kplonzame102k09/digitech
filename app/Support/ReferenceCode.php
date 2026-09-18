<?php

namespace App\Support;

final class ReferenceCode
{
    private const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const DIGITS = '0123456789';

    /**
     * Generate an uppercase A-Z0-9 reference code that always contains at
     * least one letter and at least one digit.
     */
    public static function generate(int $length = 6): string
    {
        $length = max(2, $length);

        $pool = self::LETTERS.self::DIGITS;
        $poolMax = strlen($pool) - 1;

        $code = self::LETTERS[random_int(0, strlen(self::LETTERS) - 1)]
            .self::DIGITS[random_int(0, strlen(self::DIGITS) - 1)];

        for ($i = 2; $i < $length; $i++) {
            $code .= $pool[random_int(0, $poolMax)];
        }

        return str_shuffle($code);
    }
}
