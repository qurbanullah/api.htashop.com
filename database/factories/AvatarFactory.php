<?php

namespace Database\Factories;

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Avatar>
 */
class AvatarFactory extends Factory
{
    protected $model = Avatar::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'avatareable_id' => fn () => User::factory()->create()->id,
            'avatareable_type' => User::class,
            'type' => fake()->randomElement(['original', 'thumb', 'small', 'medium', 'large']),
            'path' => 'avatars/' . fake()->uuid() . '.jpg',
            'original_filename' => fake()->word() . '.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => fake()->numberBetween(1024, 512000),
            'width' => fake()->numberBetween(100, 800),
            'height' => fake()->numberBetween(100, 800),
            'metadata' => ['optimized' => true],
        ];
    }
}
