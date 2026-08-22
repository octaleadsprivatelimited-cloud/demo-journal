<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContactStatus;
use App\Models\ContactSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactSubmission> */
class ContactSubmissionFactory extends Factory
{
    protected $model = ContactSubmission::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->optional()->numerify('+1-###-###-####'),
            'subject' => fake()->sentence(6),
            'message' => fake()->paragraphs(3, true),
            'category' => fake()->randomElement(['editorial', 'subscription', 'permissions', 'general']),
            'status' => fake()->randomElement(ContactStatus::cases()),
        ];
    }
}
