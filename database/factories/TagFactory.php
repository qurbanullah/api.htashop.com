<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(rand(1, 2), true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'type' => 'product',
            'usage_count' => 0,
        ];
    }

    /**
     * Create a tag with specific name
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'slug' => Str::slug($name),
        ]);
    }
}
