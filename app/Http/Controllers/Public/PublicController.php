<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\PublicationSettings;
use Illuminate\Database\Eloquent\Builder;

abstract class PublicController extends Controller
{
    public function __construct(private readonly PublicationSettings $publicationSettings) {}

    protected function publishedArticles(): Builder
    {
        return Article::query()
            ->published()
            ->with([
                'category:id,name,slug',
                'authors:id,name,slug,designation,organization,avatar_path,is_verified',
                'tags:id,name,slug',
            ]);
    }

    /** @return array<string, mixed> */
    protected function publicViewData(array $data = []): array
    {
        $data['site'] ??= $this->siteSettings();

        return $data;
    }

    /** @return array<string, mixed> */
    protected function siteSettings(): array
    {
        return $this->publicationSettings->site();
    }

    protected function featureEnabled(string $feature): bool
    {
        return $this->publicationSettings->featureEnabled($feature);
    }
}
