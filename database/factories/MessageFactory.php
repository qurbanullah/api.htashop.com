<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'message' => $this->generateRealisticMessage(),
        ];
    }

    /**
     * Generate realistic ticket messages
     */
    private function generateRealisticMessage(): string
    {
        $messageTypes = [
            // Initial problem descriptions
            [
                "I'm experiencing issues with {feature}. {problem_description}",
                "Having trouble with {feature}. {problem_description}",
                "There seems to be a problem with {feature}. {problem_description}",
                "I've encountered an issue where {problem_description}",
                "The {feature} is not working as expected. {problem_description}",
            ],
            // Follow-up messages
            [
                "I've tried {solution_attempt} but the issue persists.",
                "Additional information: {additional_info}",
                "This is affecting {impact_description}.",
                "I can reproduce this by {reproduction_steps}.",
                "This started happening {time_reference}.",
            ],
            // Status updates
            [
                "Any updates on this issue?",
                "Is there an estimated time for resolution?",
                "I'm still experiencing this problem.",
                "The issue seems to be resolved on my end.",
                "Thank you for the quick response!",
            ],
            // Support responses
            [
                "Thank you for reporting this issue. We're investigating it now.",
                "Could you please provide more details about {detail_request}?",
                "We've identified the issue and are working on a fix.",
                "This has been resolved in the latest update. Please try again.",
                "Please try {suggested_solution} and let us know if this helps.",
            ],
            // Technical details
            [
                "Error message: {error_message}",
                "Browser: {browser_info}",
                "Operating System: {os_info}",
                "Steps to reproduce: {reproduction_steps}",
                "Expected behavior: {expected_behavior}",
            ],
        ];

        $selectedType = fake()->randomElement($messageTypes);
        $template = fake()->randomElement($selectedType);

        // Replace placeholders with realistic content
        $replacements = [
            '{feature}' => fake()->randomElement([
                'login system', 'dashboard', 'license activation', 'user management',
                'file upload', 'notification system', 'search functionality', 'payment processing',
                'API integration', 'email system', 'backup feature', 'reporting module'
            ]),
            '{problem_description}' => fake()->randomElement([
                'It shows an error message when I try to proceed',
                'The page loads but displays incorrect information',
                'It crashes when I click the submit button',
                'The response time is extremely slow',
                'It redirects to a blank page',
                'The data is not saving properly',
                'It shows a 500 internal server error',
                'The interface is not responsive on mobile devices'
            ]),
            '{solution_attempt}' => fake()->randomElement([
                'clearing my browser cache', 'restarting the application',
                'using a different browser', 'disabling browser extensions',
                'refreshing the page multiple times', 'logging out and back in',
                'checking my internet connection', 'updating my browser'
            ]),
            '{additional_info}' => fake()->randomElement([
                'This happens on both Chrome and Firefox',
                'Other users in my organization are experiencing the same issue',
                'The problem occurs only during peak hours',
                'This worked fine until the recent update',
                'I can provide screenshots if needed',
                'The issue is intermittent'
            ]),
            '{impact_description}' => fake()->randomElement([
                'our daily operations', 'multiple users in our team',
                'our ability to process orders', 'customer satisfaction',
                'our productivity', 'our client deliverables',
                'our business continuity', 'our user experience'
            ]),
            '{reproduction_steps}' => fake()->randomElement([
                'going to the settings page and clicking save',
                'uploading a file larger than 10MB',
                'trying to access the admin panel',
                'submitting a form with special characters',
                'navigating from the dashboard to reports',
                'attempting to download the monthly report'
            ]),
            '{time_reference}' => fake()->randomElement([
                'after the last update', 'since yesterday',
                'this morning', 'over the weekend',
                'since we upgraded our plan', 'after changing our password',
                'when we switched to the new version', 'following the system maintenance'
            ]),
            '{detail_request}' => fake()->randomElement([
                'your browser version and operating system',
                'the exact error message you\'re seeing',
                'the steps you took before this happened',
                'your account information',
                'the time when this occurred',
                'any recent changes to your setup'
            ]),
            '{suggested_solution}' => fake()->randomElement([
                'clearing your browser cache and cookies',
                'using an incognito/private browsing window',
                'updating your browser to the latest version',
                'disabling any ad-blocking extensions',
                'checking your firewall settings',
                'switching to a different network connection'
            ]),
            '{error_message}' => fake()->randomElement([
                'ERR_CONNECTION_REFUSED',
                'Invalid credentials provided',
                'Session has expired',
                'Permission denied',
                'File not found',
                'Database connection failed',
                'Request timeout',
                'Invalid input format'
            ]),
            '{browser_info}' => fake()->randomElement([
                'Chrome 120.0.6099.109',
                'Firefox 121.0',
                'Safari 17.2.1',
                'Edge 120.0.2210.61',
                'Opera 106.0.4998.19'
            ]),
            '{os_info}' => fake()->randomElement([
                'Windows 11 Pro',
                'macOS Sonoma 14.2',
                'Ubuntu 22.04 LTS',
                'iOS 17.2.1',
                'Android 14',
                'Windows 10 Home'
            ]),
            '{expected_behavior}' => fake()->randomElement([
                'The page should load without errors',
                'The data should be saved successfully',
                'I should receive a confirmation message',
                'The file should upload within 30 seconds',
                'The form should validate the input correctly',
                'The system should send a notification email'
            ]),
        ];

        $message = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Add some variation in message length
        if (fake()->boolean(30)) {
            $additionalContext = fake()->randomElement([
                ' Please let me know if you need any additional information.',
                ' I would appreciate a quick resolution as this is affecting our work.',
                ' Thank you for your assistance with this matter.',
                ' Looking forward to your response.',
                ' Any help would be greatly appreciated.',
                ' This is quite urgent for our business operations.',
            ]);
            $message .= $additionalContext;
        }

        return $message;
    }

    /**
     * Create a message for a specific ticket
     */
    public function forTicket($ticket): static
    {
        return $this->state(function (array $attributes) use ($ticket) {
            return [
                'messageable_type' => get_class($ticket),
                'messageable_id' => $ticket->id,
            ];
        });
    }

    /**
     * Create a message from a specific user
     */
    public function fromUser($user): static
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'user_id' => $user->id,
            ];
        });
    }

    /**
     * Create an initial message (first message in a ticket)
     */
    public function initial(): static
    {
        return $this->state(function (array $attributes) {
            $initialMessages = [
                "I need help with license activation. The system shows an error when I try to activate my license key.",
                "I'm unable to access the admin panel after the recent update. Getting a 403 forbidden error.",
                "The file upload feature is not working properly. Files get stuck at 50% upload progress.",
                "I'm experiencing slow performance when loading the dashboard. It takes over 30 seconds to load.",
                "There's an issue with email notifications. I'm not receiving any notification emails.",
                "The search functionality returns incorrect results. It's showing outdated data.",
                "I can't generate reports. The system shows 'Export failed' error message.",
                "The user management section is not saving changes. Settings revert after refresh.",
                "I'm getting frequent timeout errors when processing large datasets.",
                "The mobile app crashes when trying to sync data with the server.",
            ];

            return [
                'message' => fake()->randomElement($initialMessages),
            ];
        });
    }

    /**
     * Create a support response message
     */
    public function supportResponse(): static
    {
        return $this->state(function (array $attributes) {
            $supportResponses = [
                "Thank you for contacting us. We're looking into this issue and will get back to you shortly.",
                "We've received your report and have escalated it to our technical team for investigation.",
                "Could you please provide your license key and system information so we can better assist you?",
                "We've identified the issue in our logs. A fix will be deployed in the next update.",
                "Please try clearing your browser cache and attempt the operation again.",
                "We've resolved this issue on our end. Please check if it's working now.",
                "This appears to be related to server maintenance. The issue should be resolved now.",
                "We'll need to schedule a quick call to troubleshoot this further. What times work for you?",
            ];

            return [
                'message' => fake()->randomElement($supportResponses),
            ];
        });
    }
}
