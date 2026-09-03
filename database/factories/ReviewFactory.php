<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReviewRecommendation;
use App\Enums\ReviewStatus;
use App\Models\Article;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Review> */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        $completed = fake()->boolean(55);

        return [
            'article_id' => Article::factory(),
            'reviewer_id' => User::factory(),
            'assigned_by_id' => User::factory(),
            'status' => $completed ? ReviewStatus::Completed : ReviewStatus::Assigned,
            'recommendation' => $completed ? fake()->randomElement(ReviewRecommendation::cases()) : null,
            'comments_to_author' => $completed ? fake()->paragraphs(2, true) : null,
            'confidential_comments' => $completed ? fake()->optional()->paragraph() : null,
            'due_at' => fake()->dateTimeBetween('now', '+30 days'),
            'completed_at' => $completed ? now() : null,
        ];
    }
}
