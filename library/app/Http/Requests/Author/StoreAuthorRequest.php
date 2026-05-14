<?php

declare(strict_types=1);

namespace App\Http\Requests\Author;

use Illuminate\Foundation\Http\FormRequest;

class StoreAuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('authors.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'unique:authors', 'string', 'max:255'],
        ];
    }
}
