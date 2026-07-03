<?php

namespace App\Rules;

use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    /**
     * The password strength rule shared by registration, password reset,
     * and profile password-change requests.
     */
    public static function rules(): Password
    {
        return Password::min(10)->letters()->mixedCase()->numbers();
    }
}
