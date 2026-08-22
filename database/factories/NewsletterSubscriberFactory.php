<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriberStatus;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NewsletterSubscriber> */
class NewsletterSubscriberFactory extends Factory
{
    protected $model = NewsletterSubscriber::class;

    public function definition(): array
    {
        return [
            'name' => fake()->optional()->name(),
            'email' => fake()->unique()->safeEmail(),
            'status' => SubscriberStatus::Active,
            'source' => fake()->randomElement(['footer', 'article', 'homepage']),
            'subscribed_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'confirmed_at' => now(),
        ];
    }
}
