<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\Article;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Submission> */
class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'submitted_by_id' => User::factory(),
            'round' => 1,
            'status' => SubmissionStatus::Pending,
            'cover_letter' => fake()->paragraph(),
            'submitted_at' => now(),
        ];
    }
}
