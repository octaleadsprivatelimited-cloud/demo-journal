<?php

namespace App\Services;

use App\Models\Article;
use App\Services\Contracts\DoiProvider;

class ManualDoiProvider implements DoiProvider
{
    public function prepare(Article $article): array
    {
        return [
            'journal' => config('workflow.journal'), 'issn' => config('workflow.issn'),
            'title' => $article->title, 'article_number' => $article->article_number,
            'authors' => $article->authors()->get()->map(fn ($author) => ['name' => $author->name, 'affiliation' => $author->organization, 'orcid' => $author->orcid])->all(),
            'abstract' => $article->abstract, 'volume' => $article->volume, 'issue' => $article->issue,
            'publication_date' => data_get($article->workflow->data, 'metadata.publication_date'),
            'landing_url' => route('articles.show', $article), 'mode' => 'manual',
        ];
    }
}
