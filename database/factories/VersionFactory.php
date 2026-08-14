<?php

namespace Database\Factories;

use App\Models\Software;
use App\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Version>
 */
class VersionFactory extends Factory
{
    protected $model = Version::class;

    public function definition(): array
    {
        $software = Software::factory()->create();

        return [
            'software_id' => $software->id,
            'version_number' => fake()->numerify('v#.##'),
            'name' => fake()->words(3, true),
            'summary' => fake()->sentence(10),
            'description' => fake()->paragraph(3),
            'release_date' => fake()->date(),
            'is_active' => true,
            'metadata' => [
                'release_type' => fake()->randomElement(['stable', 'beta', 'preview']),
            ],
        ];
    }
}
