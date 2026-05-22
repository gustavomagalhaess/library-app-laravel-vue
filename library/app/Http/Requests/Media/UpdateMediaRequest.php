<?php

declare(strict_types=1);

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The `can:media.update,type` route middleware already gates this
        // endpoint; we double-check via the same Gate so the FormRequest is
        // self-contained (and safe if it ever gets reused outside the route).
        return $this->user()?->can('media.update', (string) $this->route('type')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'publication_year' => ['required', 'integer', 'between:1000,'.((int) date('Y') + 1)],
            // File is optional on update — a present file replaces the previous one.
            'file'             => ['sometimes', 'nullable', 'file', 'mimes:pdf', 'max:51200'],

            'authors'          => ['array'],
            'authors.ids'      => ['array'],
            'authors.ids.*'    => ['integer', 'exists:authors,id'],
            'authors.new'      => ['array'],
            'authors.new.*'    => ['string', 'max:255'],

            'classifications'       => ['array'],
            'classifications.ids'   => ['array'],
            'classifications.ids.*' => ['integer', 'exists:classifications,id'],
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
