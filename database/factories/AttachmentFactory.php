<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attachable_type' => User::class,
            'attachable_id' => fn () => User::factory()->create()->id,
            'name' => fake()->word(),
            'filename' => fake()->word() . '.pdf',
            'path' => 'attachments/' . fake()->uuid() . '.pdf',
            'type' => fake()->randomElement(['document', 'image', 'other']),
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 1024000),
            'extension' => 'pdf',
            'description' => fake()->sentence(),
            'is_confidential' => fake()->boolean(),
            'version' => 1,
            'sort_order' => 0,
            'metadata' => [],
            'uploaded_by' => fn () => User::factory()->create()->id,
        ];
    }
}
