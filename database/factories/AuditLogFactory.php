<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auditable_type' => User::class,
            'auditable_id' => fn () => User::factory()->create()->id,
            'user_id' => fn () => User::factory()->create()->id,
            'event' => fake()->randomElement(['created', 'updated', 'deleted']),
            'auditable_type_name' => 'User',
            'old_values' => ['name' => fake()->name()],
            'new_values' => ['name' => fake()->name()],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'description' => fake()->sentence(),
        ];
    }
}
