<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Article> */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        $status = fake()->randomElement(ArticleStatus::cases());
        $publishedAt = $status === ArticleStatus::Published ? fake()->dateTimeBetween('-18 months', 'now') : null;

        return [
            'category_id' => Category::factory(),
            'created_by_id' => User::factory(),
            'title' => fake()->unique()->sentence(fake()->numberBetween(6, 11)),
            'subtitle' => fake()->optional(0.7)->sentence(),
            'abstract' => fake()->paragraphs(2, true),
            'content' => collect(fake()->paragraphs(fake()->numberBetween(8, 16)))->map(fn (string $paragraph) => '<p>'.$paragraph.'</p>')->implode("\n"),
            'keywords' => fake()->words(5),
            'references' => fake()->sentences(4),
            'publication_type' => fake()->randomElement(['research', 'analysis', 'opinion', 'review']),
            'status' => $status,
            'is_featured' => fake()->boolean(15),
            'is_trending' => fake()->boolean(20),
            'comments_enabled' => true,
            'pdf_download_enabled' => fake()->boolean(70),
            'view_count' => $status === ArticleStatus::Published ? fake()->numberBetween(50, 30_000) : 0,
            'submitted_at' => in_array($status, [ArticleStatus::Draft], true) ? null : fake()->dateTimeBetween('-2 years', '-1 month'),
            'approved_at' => in_array($status, [ArticleStatus::Approved, ArticleStatus::Scheduled, ArticleStatus::Published], true) ? fake()->dateTimeBetween('-1 year', '-1 day') : null,
            'scheduled_for' => $status === ArticleStatus::Scheduled ? fake()->dateTimeBetween('+1 day', '+3 months') : null,
            'published_at' => $publishedAt,
            'rejected_at' => $status === ArticleStatus::Rejected ? fake()->dateTimeBetween('-1 year', 'now') : null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ArticleStatus::Draft, 'submitted_at' => null, 'published_at' => null]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ArticleStatus::Published,
            'submitted_at' => now()->subMonth(),
            'approved_at' => now()->subWeek(),
            'published_at' => now()->subDay(),
            'scheduled_for' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => ArticleStatus::Scheduled,
            'submitted_at' => now()->subMonth(),
            'approved_at' => now()->subDay(),
            'scheduled_for' => now()->addDay(),
        ]);
    }
}
