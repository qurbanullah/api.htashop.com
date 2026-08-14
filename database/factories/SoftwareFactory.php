<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Software>
 */
class SoftwareFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // $name = fake()->randomElement([
        //     'Real3D CAD', 'Real3D Render', 'Real3D Animation', 'Real3D Simulator',
        //     'Real3D Architect', 'Real3D Mesh', 'Real3D VR', 'Real3D Cloud',
        //     'Real3D Mobile', 'Real3D Analytics', 'Real3D Plugin', 'Real3D Core'
        // ]) . ' ' . fake()->randomElement(['Pro', 'Studio', 'Suite', 'Tools', 'Manager', 'Engine']);

        $name = fake()->randomElement([
            'Volvicon', 'Volvicon Render', 'Volvicon Simulator', 'Volvicon Mesh', 'Real3D VR', 'Volvicon Core'
        ]);

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'summary' => fake()->sentence(8),
            'description' => fake()->paragraphs(3, true),
            'image' => 'software/' . \Illuminate\Support\Str::slug($name) . '.png',
            'is_active' => fake()->boolean(85), // 85% chance of being active
            'sorting' => fake()->numberBetween(1, 100),
        ];
    }

    /**
     * Indicate that the software is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the software is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
