<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Emails are stored lowercase so "A@x.com" and "a@x.com" are one account
     * on every database, whatever its collation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => Str::lower($this->email)]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
