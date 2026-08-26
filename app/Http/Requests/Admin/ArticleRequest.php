<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['keywords', 'references', 'focus_keywords'] as $field) {
            if (is_string($this->input($field))) {
                $parts = preg_split($field === 'references' ? '/\r\n|\r|\n/' : '/[,\r\n]+/', $this->input($field)) ?: [];
                $this->merge([$field => array_values(array_filter(array_map('trim', $parts)))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:240'], 'subtitle' => ['nullable', 'string', 'max:300'],
            'excerpt' => ['nullable', 'string', 'max:1000'], 'abstract' => ['nullable', 'string', 'max:10000'],
            'content' => ['required', 'string', 'max:1000000'], 'category_id' => ['nullable', 'exists:categories,id'],
            'journal_issue_id' => ['nullable', 'exists:journal_issues,id'],
            'created_by_id' => ['required', 'exists:users,id'], 'assigned_editor_id' => ['nullable', 'exists:users,id'],
            'authors' => ['nullable', 'array', 'max:20'], 'authors.*' => ['integer', 'distinct', 'exists:authors,id'],
            'tags' => ['nullable', 'array', 'max:30'], 'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
            'keywords' => ['nullable', 'array', 'max:30'], 'keywords.*' => ['string', 'max:80'],
            'references' => ['nullable', 'array', 'max:100'], 'references.*' => ['string', 'max:2000'],
            'doi' => ['nullable', 'string', 'max:255'], 'publication_type' => ['required', 'in:article,research,review,essay,case-study,editorial'],
            'volume' => ['nullable', 'string', 'max:40'], 'issue' => ['nullable', 'string', 'max:40'], 'article_number' => ['nullable', 'string', 'max:80'],
            'received_date' => ['nullable', 'date'], 'revised_date' => ['nullable', 'date'], 'accepted_date' => ['nullable', 'date'],
            'license' => ['nullable', 'string', 'max:255'], 'copyright_statement' => ['nullable', 'string', 'max:2000'],
            'publication_notice' => ['nullable', 'in:none,correction,retraction,expression_of_concern'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'manuscript_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'comments_enabled' => ['sometimes', 'boolean'], 'pdf_download_enabled' => ['sometimes', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'], 'meta_description' => ['nullable', 'string', 'max:1000'],
            'canonical_url' => ['nullable', 'url:http,https', 'max:2048'], 'focus_keywords' => ['nullable', 'array', 'max:20'],
            'focus_keywords.*' => ['string', 'max:80'], 'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:1000'], 'twitter_card' => ['nullable', 'in:summary,summary_large_image'],
        ];
    }
}
