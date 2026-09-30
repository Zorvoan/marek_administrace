<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation shared by creating and editing a post. Who may edit is decided
 * by PostPolicy, not here.
 */
class PostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
