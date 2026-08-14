<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Ticket;
use App\Enums\StatusEnum;
use App\Enums\PriorityEnum;
use App\Enums\SeverityEnum;
use App\Enums\ReproducibilityEnum;
use App\Enums\StypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Ticket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate different types of ticket descriptions
        $ticketTypes = [
            'bug' => [
                'title' => fake()->randomElement([
                    'Application crashes when clicking submit button',
                    'Login form not working properly',
                    'Data not saving to database',
                    'Page loading indefinitely',
                    'Form validation errors not displaying',
                    'Search functionality returns no results',
                    'Email notifications not being sent',
                    'File upload feature broken',
                    'Dashboard statistics not updating',
                    'Export feature generates empty files'
                ]),
                'description' => fake()->paragraph(mt_rand(3, 6)),
                'steps' => fake()->randomElement([
                    "1. Open the application\n2. Navigate to the form\n3. Fill out required fields\n4. Click submit\n5. Application crashes",
                    "1. Go to login page\n2. Enter valid credentials\n3. Click login button\n4. Error message appears",
                    "1. Open user profile\n2. Edit information\n3. Save changes\n4. Data is not persisted"
                ])
            ],
            'feature' => [
                'title' => fake()->randomElement([
                    'Add dark mode support',
                    'Implement advanced search filters',
                    'Add export to PDF functionality',
                    'Create mobile app version',
                    'Add two-factor authentication',
                    'Implement real-time notifications',
                    'Add bulk operations support',
                    'Create API integration',
                    'Add user role management',
                    'Implement automated backups'
                ]),
                'description' => fake()->paragraph(mt_rand(2, 4)),
                'steps' => null
            ],
            'question' => [
                'title' => fake()->randomElement([
                    'How to configure email settings?',
                    'What are the system requirements?',
                    'How to backup my data?',
                    'Can I integrate with third-party services?',
                    'How to customize the dashboard?',
                    'What payment methods are supported?',
                    'How to manage user permissions?',
                    'Can I export my data?',
                    'How to set up automated reports?',
                    'What is the data retention policy?'
                ]),
                'description' => fake()->paragraph(mt_rand(1, 3)),
                'steps' => null
            ]
        ];

        $type = fake()->randomElement(['bug', 'feature', 'question']);
        $ticketData = $ticketTypes[$type];

        $status = fake()->randomElement(StatusEnum::cases());
        $isResolved = in_array($status, [StatusEnum::RESOLVED, StatusEnum::CLOSED, StatusEnum::ARCHIVED]);

        // Create timestamps in the correct order using Carbon/Laravel's now() helper
        $createdAt = now()->subDays(fake()->numberBetween(1, 180))->format('Y-m-d H:i:s');
        $updatedAt = now()->subDays(fake()->numberBetween(0, 30))->format('Y-m-d H:i:s');
        $resolvedOn = $isResolved ? now()->subDays(fake()->numberBetween(1, 30))->format('Y-m-d H:i:s') : null;
        $archivedOn = $status === StatusEnum::ARCHIVED ? now()->subDays(fake()->numberBetween(1, 30))->format('Y-m-d H:i:s') : null;

        return [
            'uuid' => fake()->uuid(),
            'user_id' => User::factory(),
            'title' => $ticketData['title'],
            'slug' => Str::slug($ticketData['title']) . '-' . Str::random(6),
            'stype' => fake()->randomElement(StypeEnum::cases())->value,
            'severity' => fake()->randomElement(SeverityEnum::cases())->value,
            'reproducibility' => fake()->randomElement(ReproducibilityEnum::cases())->value,
            'priority' => fake()->randomElement(PriorityEnum::cases())->value,
            'status' => $status->value,
            'is_visible' => fake()->boolean(90), // 90% visible
            'is_resolved' => $isResolved,
            'is_locked' => fake()->boolean(5), // 5% locked
            'description' => $ticketData['description'],
            'steps_to_reproduce' => $ticketData['steps'],
            'additional_information' => fake()->optional(0.7)->paragraph(),
            'resolved_on' => $resolvedOn,
            'archived_on' => $archivedOn,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * Indicate that the ticket is open.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusEnum::OPEN->value,
            'is_resolved' => false,
            'resolved_on' => null,
            'archived_on' => null,
        ]);
    }

    /**
     * Indicate that the ticket is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusEnum::CLOSED->value,
            'is_resolved' => true,
            'resolved_on' => fake()->dateTimeBetween('-30 days', 'now'),
            'archived_on' => null,
        ]);
    }

    /**
     * Indicate that the ticket is resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusEnum::RESOLVED->value,
            'is_resolved' => true,
            'resolved_on' => fake()->dateTimeBetween('-30 days', 'now'),
            'archived_on' => null,
        ]);
    }

    /**
     * Indicate that the ticket is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusEnum::ARCHIVED->value,
            'is_resolved' => true,
            'resolved_on' => fake()->dateTimeBetween('-60 days', '-30 days'),
            'archived_on' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the ticket is a bug report.
     */
    public function bug(): static
    {
        return $this->state(fn (array $attributes) => [
            'stype' => StypeEnum::BUG->value,
            'severity' => fake()->randomElement([
                SeverityEnum::MINOR->value,
                SeverityEnum::MAJOR->value,
                SeverityEnum::CRASH->value,
                SeverityEnum::BLOCK->value
            ]),
            'priority' => fake()->randomElement([
                PriorityEnum::NORMAL->value,
                PriorityEnum::HIGH->value,
                PriorityEnum::URGENT->value
            ]),
        ]);
    }

    /**
     * Indicate that the ticket is a feature request.
     */
    public function feature(): static
    {
        return $this->state(fn (array $attributes) => [
            'stype' => StypeEnum::FEATURE->value,
            'severity' => SeverityEnum::FEATURE->value,
            'priority' => fake()->randomElement([
                PriorityEnum::LOW->value,
                PriorityEnum::NORMAL->value,
                PriorityEnum::HIGH->value
            ]),
        ]);
    }
}
