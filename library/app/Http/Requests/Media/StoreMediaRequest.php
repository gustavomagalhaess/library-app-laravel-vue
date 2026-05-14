<?php

declare(strict_types=1);

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the fields common to every media type (title, publication_year,
 * file, authors). Type-specific fields (e.g. `pages` for a Book) are
 * forwarded to the respective domain service which performs its own
 * validation there.
 */
class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The `can:media.create,type` route middleware already gates this
        // endpoint; we double-check via the same Gate so the FormRequest is
        // self-contained (and safe if it ever gets reused outside the route).
        return $this->user()?->can('media.create', (string) $this->route('type')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'publication_year' => ['required', 'integer', 'between:1000,'.((int) date('Y') + 1)],
            'file'             => ['required', 'file', 'mimes:pdf', 'max:51200'], // 50 MB

            // Existing authors selected from the dropdown.
            'authors'          => ['array'],
            'authors.ids'      => ['array'],
            'authors.ids.*'    => ['integer', 'exists:authors,id'],

            // Newly typed-in authors that should be created on the fly.
            'authors.new'      => ['array'],
            'authors.new.*'    => ['string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $ids = $this->input('authors.ids', []);
            $new = $this->input('authors.new', []);
            if (count($ids) === 0 && count(array_filter($new, fn ($n) => trim((string) $n) !== '')) === 0) {
                $v->errors()->add('authors', 'At least one author is required.');
            }
        });
    }
}
