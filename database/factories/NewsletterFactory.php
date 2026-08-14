<?php

namespace Database\Factories;

use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NewsletterFactory extends Factory
{
    protected $model = Newsletter::class;

    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'title' => $this->faker->sentence(),
            'slug' => $this->faker->slug(),
            'excerpt' => $this->faker->paragraph(),
            'content' => '<h1>' . $this->faker->sentence() . '</h1><p>' . $this->faker->paragraph() . '</p>',
            'featured_image' => $this->faker->imageUrl(800, 600, 'newsletter', true),
            'images' => [
                $this->faker->imageUrl(400, 300, 'content1', true),
                $this->faker->imageUrl(400, 300, 'content2', true),
            ],
            'status' => $this->faker->randomElement(['draft', 'scheduled', 'published', 'sent']),
            'is_published_as_blog' => $this->faker->boolean(30),
            'scheduled_at' => $this->faker->optional(0.3)->dateTimeBetween('now', '+1 month'),
            'sent_at' => $this->faker->optional(0.2)->dateTimeBetween('-1 month', 'now'),
            'recipients' => [
                $this->faker->email(),
                $this->faker->email(),
                $this->faker->email(),
            ],
            'recipients_count' => $this->faker->numberBetween(10, 1000),
            'sent_count' => $this->faker->numberBetween(0, 1000),
            'opened_count' => $this->faker->numberBetween(0, 500),
            'clicked_count' => $this->faker->numberBetween(0, 100),
            'tags' => [
                $this->faker->word(),
                $this->faker->word(),
            ],
            'categories' => [
                $this->faker->word(),
                $this->faker->word(),
            ],
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'scheduled_at' => null,
            'sent_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+1 month'),
            'sent_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'sent_at' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'sent_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function publishedAsBlog(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published_as_blog' => true,
        ]);
    }
}
