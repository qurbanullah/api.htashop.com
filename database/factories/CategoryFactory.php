<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->words(rand(1, 3), true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->paragraph(),
            'parent_id' => null,
            'image' => null,
            'is_active' => fake()->boolean(90),
            'sort_order' => fake()->numberBetween(0, 100),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the category should be a child of another category
     */
    public function withParent(int $parentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }
}
