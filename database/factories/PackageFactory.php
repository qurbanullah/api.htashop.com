<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $packageTypes = [
            'Standard', 'Professional', 'Premium', 'Enterprise', 'Basic', 'Advanced',
            'Studio', 'Ultimate', 'Student', 'Academic', 'Commercial', 'Startup',
            'Demo', 'Trial', 'Researcher', 'Developer', 'Personal', 'Business'
        ];

        $name = fake()->randomElement($packageTypes) . ' ' . fake()->randomElement(['Edition', 'Package', 'Plan', 'License']);

        // Package descriptions based on type
        $descriptions = [
            'Essential features for individual users and small projects',
            'Advanced tools for professional workflows and medium-scale projects',
            'Comprehensive feature set for studios and creative professionals',
            'Complete solution with unlimited access to all features and premium support',
            'Affordable package designed specifically for students and educational use',
            'Research-focused package with specialized tools for academic institutions',
            'Limited-time demonstration package to explore core functionality',
            'Time-limited trial access to evaluate the full feature set',
            'Professional-grade tools for serious creative work',
            'Enterprise-level package with advanced collaboration and management features'
        ];

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'summary' => fake()->sentence(6),
            'description' => fake()->randomElement($descriptions),
            'image' => 'packages/' . \Illuminate\Support\Str::slug($name) . '.png',
            'is_active' => fake()->boolean(90), // 90% chance of being active
            'sorting' => fake()->numberBetween(1, 20),
        ];
    }

    /**
     * Indicate that the package is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the package is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a standard package.
     */
    public function standard(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Standard',
            'slug' => 'standard',
            'summary' => 'Essential features for individual users and small projects',
            'description' => 'Standard package includes core functionality, basic support, and access to essential tools for individual users and small-scale projects.',
        ]);
    }

    /**
     * Create a professional package.
     */
    public function professional(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Professional',
            'slug' => 'professional',
            'summary' => 'Advanced tools for professional workflows and commercial use',
            'description' => 'Professional package offers advanced features, priority support, collaboration tools, and commercial licensing for professional users.',
        ]);
    }

    /**
     * Create a studio package.
     */
    public function studio(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Studio',
            'slug' => 'studio',
            'summary' => 'Comprehensive suite for creative studios and teams',
            'description' => 'Studio package provides comprehensive tools for creative teams, advanced rendering capabilities, and studio-level collaboration features.',
        ]);
    }

    /**
     * Create an ultimate package.
     */
    public function ultimate(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Ultimate',
            'slug' => 'ultimate',
            'summary' => 'Complete solution with unlimited access to all features',
            'description' => 'Ultimate package includes all features, unlimited usage, premium support, early access to new features, and enterprise-level capabilities.',
        ]);
    }

    /**
     * Create a student package.
     */
    public function student(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Student',
            'slug' => 'student',
            'summary' => 'Educational discount package for students and learners',
            'description' => 'Student package offers significantly discounted access to core features for verified students, educators, and educational institutions.',
        ]);
    }

    /**
     * Create a researcher package.
     */
    public function researcher(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Researcher',
            'slug' => 'researcher',
            'summary' => 'Specialized package for academic research and institutions',
            'description' => 'Researcher package provides specialized tools for academic research, data analysis capabilities, and institutional licensing options.',
        ]);
    }

    /**
     * Create a demo package.
     */
    public function demo(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Demo',
            'slug' => 'demo',
            'summary' => 'Limited demonstration package to explore core features',
            'description' => 'Demo package allows users to explore core functionality with limited features and watermarked output for evaluation purposes.',
        ]);
    }

    /**
     * Create a trial package.
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Trial',
            'slug' => 'trial',
            'summary' => 'Time-limited trial access to evaluate the full feature set',
            'description' => 'Trial package provides temporary access to all features for a limited time period, allowing users to fully evaluate before purchase.',
        ]);
    }
}
