<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Article */
final class ArticleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'excerpt' => $this->excerpt,
            'abstract' => $this->abstract,
            'content' => $this->when($request->routeIs('api.articles.show'), $this->content),
            'publication_type' => $this->publication_type,
            'doi' => $this->doi,
            'status' => $this->status instanceof ArticleStatus ? $this->status->value : $this->status,
            'reading_time_minutes' => $this->reading_time_minutes,
            'view_count' => $this->view_count,
            'featured_image_url' => $this->featured_image_path
                ? Storage::disk(config('publication.uploads.disk'))->url($this->featured_image_path)
                : null,
            'pdf_url' => $this->when(
                $this->pdf_path && $this->pdf_download_enabled,
                url('/article/'.$this->slug.'/pdf')
            ),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null),
            'authors' => $this->whenLoaded('authors', fn () => $this->authors->map(fn ($author) => [
                'name' => $author->name,
                'slug' => $author->slug,
                'organization' => $author->organization,
            ])->values()),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->values()),
            'keywords' => $this->keywords ?? [],
            'published_at' => $this->published_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
            'url' => url('/article/'.$this->slug),
        ];
    }
}
