<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CardiologyHomepageSetup
{
    private const DISCIPLINES = [
        'preventive-cardiology' => ['Preventive Cardiology', 'Cardiovascular prevention and risk assessment.'],
        'vascular-medicine' => ['Vascular Medicine', 'Research on vascular health and vascular disease.'],
        'interventional-cardiology' => ['Interventional Cardiology', 'Research on catheter-based cardiovascular procedures.'],
        'cardiac-electrophysiology' => ['Cardiac Electrophysiology', 'Research on cardiac rhythm and electrophysiology.'],
        'diagnostics' => ['Diagnostics', 'Cardiovascular imaging, testing and diagnostic research.'],
    ];

    private const ARTICLES = [
        'unveiling-cardio-ankle-vascular-index-an-independent-predictor-of-hypertensive-response-to-exercise',
        'prospective-non-interventional-observational-study-assessing-the-effectiveness-of-perindopril-and-perindoprilindapamide-in-a-bangladeshi-population-of-patients-with-hypertension',
    ];

    public function apply(bool $write = false): array
    {
        return DB::transaction(function () use ($write): array {
            $articles = Article::query()->published()->whereIn('slug', self::ARTICLES)->lockForUpdate()->get();
            if ($articles->count() !== count(self::ARTICLES)) {
                throw new RuntimeException('Both requested articles must already exist and be published. No content was changed.');
            }

            $retired = Category::query()->whereIn('slug', ['culture-ideas', 'culture-and-ideas', 'climate-environment', 'climate-and-environment'])
                ->orWhereIn('name', ['Culture & Ideas', 'Climate & Environment'])->lockForUpdate()->get();
            if ($retired->contains(fn ($category) => $category->articles()->exists() || $category->children()->exists())) {
                throw new RuntimeException('An old discipline still has articles or child disciplines; move them through Categories before retiring it. No content was changed.');
            }

            $existing = Category::query()->whereIn('slug', array_keys(self::DISCIPLINES))->lockForUpdate()->get();
            if ($existing->contains(fn ($category) => $category->parent_id !== null)) {
                throw new RuntimeException('A requested discipline is already nested; review its parent before changing it. No content was changed.');
            }

            $report = [
                'applied' => $write,
                'disciplines' => array_column(array_values(self::DISCIPLINES), 0),
                'retired_disciplines' => $retired->pluck('name')->all(),
                'featured_articles' => $articles->pluck('slug')->all(),
                'before' => [
                    'categories' => $existing->merge($retired)->unique('id')->map->getRawOriginal()->values()->all(),
                    'articles' => $articles->map(fn ($article) => array_intersect_key($article->getRawOriginal(), array_flip(['id', 'is_featured', 'is_homepage_latest', 'updated_at'])))->all(),
                    'created_category_ids' => [],
                ],
            ];
            if (! $write) {
                return $report;
            }

            foreach (self::DISCIPLINES as $slug => [$name, $description]) {
                $category = Category::query()->firstOrCreate(['slug' => $slug], [
                    'name' => $name, 'description' => $description, 'parent_id' => null,
                    'sort_order' => array_search($slug, array_keys(self::DISCIPLINES), true) + 1, 'is_active' => true,
                ]);
                if ($category->wasRecentlyCreated) {
                    $report['before']['created_category_ids'][] = $category->id;
                } else {
                    // Keep previously uploaded descriptions, images and SEO fields.
                    $category->update(['is_active' => true, 'sort_order' => array_search($slug, array_keys(self::DISCIPLINES), true) + 1]);
                }
            }
            foreach ($retired as $category) {
                $category->update(['is_active' => false]);
            }
            // Placement changes leave titles, manuscripts and publication status intact.
            Article::query()->whereKey($articles->modelKeys())->update(['is_featured' => true, 'is_homepage_latest' => false, 'updated_at' => now()]);

            return $report;
        });
    }

    public function restore(array $before): void
    {
        DB::transaction(function () use ($before): void {
            DB::table('categories')->whereIn('id', $before['created_category_ids'])->delete();
            foreach ($before['categories'] as $row) {
                DB::table('categories')->where('id', $row['id'])->update($row);
            }
            foreach ($before['articles'] as $row) {
                DB::table('articles')->where('id', $row['id'])->update($row);
            }
        });
    }
}
