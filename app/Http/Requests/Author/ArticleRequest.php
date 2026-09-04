<?php

declare(strict_types=1);

namespace App\Http\Requests\Author;

use Illuminate\Foundation\Http\FormRequest;

final class ArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['keywords', 'references'] as $field) {
            if (is_string($this->input($field))) {
                $separator = $field === 'keywords' ? '/[,\r\n]+/' : '/\r\n|\r|\n/';
                $values = array_values(array_filter(array_map('trim', preg_split($separator, $this->input($field)) ?: [])));
                $this->merge([$field => $values]);
            }
        }

        if (is_string($this->input('tags'))) {
            $this->merge(['tags' => array_values(array_filter(explode(',', $this->input('tags'))))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'author_details' => ['nullable', 'array', 'max:20'],
            'author_details.*.name' => ['required', 'string', 'max:200'],
            'author_details.*.email' => ['required', 'email', 'max:255'],
            'author_details.*.organization' => [$this->input('intent') === 'submit' ? 'required' : 'nullable', 'string', 'max:255'],
            'author_details.*.department' => ['nullable', 'string', 'max:255'],
            'author_details.*.country' => ['nullable', 'string', 'max:100'],
            'author_details.*.orcid' => ['nullable', 'regex:/^\\d{4}-\\d{4}-\\d{4}-\\d{3}[\\dX]$/'],
            'corresponding_index' => ['nullable', 'integer', 'min:0', 'max:19'],
            'intent' => ['nullable', 'in:draft,submit'],
            'manuscript' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'extensions:pdf,doc,docx', 'max:20480'],
            'cover_letter' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'extensions:pdf,doc,docx', 'max:20480'],
            'supplementary' => ['nullable', 'array', 'max:10'],
            'supplementary.*' => ['file', 'mimes:pdf,doc,docx', 'extensions:pdf,doc,docx', 'max:20480'],
            'response' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'extensions:pdf,doc,docx', 'max:20480'],
            'title' => ['required', 'string', 'max:240'],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'abstract' => ['nullable', 'string', 'max:10000'],
            'content' => ['nullable', 'string', 'max:1000000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
            'co_authors' => ['nullable', 'array', 'max:10'],
            'co_authors.*' => ['integer', 'distinct', 'exists:authors,id'],
            'keywords' => ['nullable', 'array', 'max:30'],
            'keywords.*' => ['string', 'max:80'],
            'references' => ['nullable', 'array', 'max:100'],
            'references.*' => ['string', 'max:2000'],
            'doi' => ['nullable', 'string', 'max:255'],
            'publication_type' => ['required', 'in:article,research,review,essay,case-study,editorial'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'manuscript_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'supporting_documents' => ['nullable', 'array', 'max:6'],
            'supporting_documents.*' => ['file', 'mimes:pdf,doc,docx', 'max:20480'],
            'change_summary' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
