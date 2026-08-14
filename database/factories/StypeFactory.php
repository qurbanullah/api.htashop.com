<?php

namespace Database\Factories;

use App\Models\Stype;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Stype>
 */
class StypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Stype::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $softwareTypes = [
            'Bug Report', 'Feature Request', 'Technical Issue', 'License Issue', 'Installation Support',
            'Performance Issue', 'Security Concern', 'Documentation Request', 'Training Request', 'General Inquiry',
            'Account Issue', 'Billing Question', 'API Support', 'Integration Help', 'Maintenance Request'
        ];

        $name = fake()->randomElement($softwareTypes);
        $slug = Str::slug($name);

        return [
            'name' => $name,
            'slug' => $slug,
            'summary' => $this->generateSummary($name),
            'description' => $this->generateDescription($name),
            'image' => 'stypes/' . strtolower(str_replace(' ', '-', $name)) . '.png',
            'is_active' => fake()->boolean(90), // 90% chance of being active
            'sorting' => fake()->numberBetween(1, 50),
        ];
    }

    /**
     * Generate a realistic summary based on support type
     */
    private function generateSummary(string $typeName): string
    {
        $summaries = [
            'Bug Report' => 'Report software defects and unexpected behavior for investigation and resolution',
            'Feature Request' => 'Request new functionality or enhancements to existing software features',
            'Technical Issue' => 'Get help with technical problems and software configuration challenges',
            'License Issue' => 'Resolve licensing problems including activation, validation, and compliance',
            'Installation Support' => 'Assistance with software installation, setup, and initial configuration',
            'Performance Issue' => 'Address software performance problems and optimization requirements',
            'Security Concern' => 'Report security vulnerabilities and implement security best practices',
            'Documentation Request' => 'Request additional documentation, guides, or technical specifications',
            'Training Request' => 'Request training sessions, tutorials, or educational materials',
            'General Inquiry' => 'General questions and information requests about software products',
            'Account Issue' => 'Resolve user account problems including access and profile management',
            'Billing Question' => 'Questions about billing, invoicing, and payment-related issues',
            'API Support' => 'Technical support for API integration and development assistance',
            'Integration Help' => 'Assistance with third-party software integration and compatibility',
            'Maintenance Request' => 'Request software maintenance, updates, and system health checks',
        ];

        return $summaries[$typeName] ?? 'Support request category for software-related assistance and inquiries';
    }

    /**
     * Generate a realistic description based on support type
     */
    private function generateDescription(string $typeName): string
    {
        $descriptions = [
            'Bug Report' => 'Use this category to report software bugs, glitches, or unexpected behavior. Include detailed steps to reproduce the issue, expected vs actual results, and system information. Our development team will investigate and prioritize fixes based on severity and impact.',

            'Feature Request' => 'Submit requests for new features or enhancements to existing functionality. Provide detailed descriptions of the desired feature, use cases, and potential benefits. Feature requests are evaluated based on user demand, technical feasibility, and product roadmap alignment.',

            'Technical Issue' => 'Get technical support for software problems including configuration errors, compatibility issues, and system integration challenges. Our technical support team provides expert guidance and step-by-step troubleshooting assistance.',

            'License Issue' => 'Resolve licensing-related problems including license activation failures, validation errors, compliance questions, and license transfer requests. Our licensing specialists ensure smooth license management and compliance.',

            'Installation Support' => 'Receive assistance with software installation, initial setup, and configuration. This includes system requirements verification, installation troubleshooting, and post-installation configuration guidance.',

            'Performance Issue' => 'Address software performance problems such as slow response times, high resource usage, memory leaks, and optimization needs. Our performance specialists help identify bottlenecks and implement solutions.',

            'Security Concern' => 'Report security vulnerabilities, request security assessments, and get guidance on implementing security best practices. All security reports are handled with high priority and confidentiality.',

            'Documentation Request' => 'Request additional documentation, user guides, API references, or technical specifications. Our documentation team creates comprehensive materials to support software usage and development.',

            'Training Request' => 'Request training sessions, workshops, tutorials, or educational materials for software users and administrators. We offer various training formats including online sessions and self-paced learning materials.',

            'General Inquiry' => 'General questions about software products, features, compatibility, pricing, or any other non-technical inquiries. Our customer service team provides comprehensive information and guidance.',

            'Account Issue' => 'Resolve user account problems including login difficulties, profile updates, access permissions, and account recovery. Our account specialists ensure secure and efficient account management.',

            'Billing Question' => 'Questions about billing statements, payment methods, invoice details, subscription management, and pricing inquiries. Our billing team provides clear explanations and resolves payment-related issues.',

            'API Support' => 'Technical support for API integration including authentication, endpoint usage, data formats, rate limits, and SDK implementation. Our API specialists provide comprehensive development assistance.',

            'Integration Help' => 'Assistance with integrating our software with third-party systems, databases, or applications. This includes compatibility assessments, configuration guidance, and troubleshooting integration issues.',

            'Maintenance Request' => 'Request software maintenance services including updates, patches, system health checks, and preventive maintenance. Our maintenance team ensures optimal software performance and reliability.',
        ];

        return $descriptions[$typeName] ?? 'This support type category helps organize and prioritize customer requests to ensure efficient resolution and appropriate resource allocation.';
    }

    /**
     * Factory state for Bug Report type
     */
    public function bugReport(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Bug Report',
            'slug' => 'bug-report',
            'summary' => 'Report software defects and unexpected behavior for investigation and resolution',
            'description' => 'Use this category to report software bugs, glitches, or unexpected behavior. Include detailed steps to reproduce the issue, expected vs actual results, and system information. Our development team will investigate and prioritize fixes based on severity and impact.',
            'image' => 'stypes/bug-report.png',
            'is_active' => true,
            'sorting' => 1,
        ]);
    }

    /**
     * Factory state for Feature Request type
     */
    public function featureRequest(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Feature Request',
            'slug' => 'feature-request',
            'summary' => 'Request new functionality or enhancements to existing software features',
            'description' => 'Submit requests for new features or enhancements to existing functionality. Provide detailed descriptions of the desired feature, use cases, and potential benefits. Feature requests are evaluated based on user demand, technical feasibility, and product roadmap alignment.',
            'image' => 'stypes/feature-request.png',
            'is_active' => true,
            'sorting' => 2,
        ]);
    }

    /**
     * Factory state for Technical Issue type
     */
    public function technicalIssue(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Technical Issue',
            'slug' => 'technical-issue',
            'summary' => 'Get help with technical problems and software configuration challenges',
            'description' => 'Get technical support for software problems including configuration errors, compatibility issues, and system integration challenges. Our technical support team provides expert guidance and step-by-step troubleshooting assistance.',
            'image' => 'stypes/technical-issue.png',
            'is_active' => true,
            'sorting' => 3,
        ]);
    }

    /**
     * Factory state for License Issue type
     */
    public function licenseIssue(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'License Issue',
            'slug' => 'license-issue',
            'summary' => 'Resolve licensing problems including activation, validation, and compliance',
            'description' => 'Resolve licensing-related problems including license activation failures, validation errors, compliance questions, and license transfer requests. Our licensing specialists ensure smooth license management and compliance.',
            'image' => 'stypes/license-issue.png',
            'is_active' => true,
            'sorting' => 4,
        ]);
    }

    /**
     * Factory state for General Inquiry type
     */
    public function generalInquiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'General Inquiry',
            'slug' => 'general-inquiry',
            'summary' => 'General questions and information requests about software products',
            'description' => 'General questions about software products, features, compatibility, pricing, or any other non-technical inquiries. Our customer service team provides comprehensive information and guidance.',
            'image' => 'stypes/general-inquiry.png',
            'is_active' => true,
            'sorting' => 5,
        ]);
    }

    /**
     * Factory state for active support types
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Factory state for inactive support types
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
