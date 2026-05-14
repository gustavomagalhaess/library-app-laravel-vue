<?php

declare(strict_types=1);

namespace App\Http\Requests\Author;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('authors.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
