<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Author;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Author> */
class AuthorFactory extends Factory
{
    protected $model = Author::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'biography' => fake()->paragraphs(2, true),
            'designation' => fake()->randomElement(['Research Fellow', 'Professor', 'Senior Analyst', 'Lecturer', 'Scientist']),
            'organization' => fake()->company(),
            'website_url' => fake()->optional()->url(),
            'social_links' => ['linkedin' => 'https://www.linkedin.com/in/'.fake()->userName()],
            'is_verified' => fake()->boolean(80),
            'is_active' => true,
        ];
    }
}
