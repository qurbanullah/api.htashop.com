<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if packages already exist
        if (Package::count() > 0) {
            $this->command->info('Package records already exist, skipping package seeding.');
            return;
        }

        $this->command->info('🌱 Seeding package records...');

        // Define comprehensive package data
        $packageData = [
            [
                'name' => 'Standard',
                'slug' => 'standard',
                'summary' => 'Essential features for individual users and small projects',
                'description' => 'Standard package includes core functionality, basic support, and access to essential tools for individual users and small-scale projects. Perfect for freelancers and small businesses starting their 3D journey.',
                'image' => 'packages/standard.png',
                'is_active' => true,
                'sorting' => 1,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'summary' => 'Advanced tools for professional workflows and commercial use',
                'description' => 'Professional package offers advanced features, priority support, collaboration tools, and commercial licensing for professional users. Includes advanced rendering, animation tools, and project management capabilities.',
                'image' => 'packages/professional.png',
                'is_active' => true,
                'sorting' => 2,
            ],
            [
                'name' => 'Studio',
                'slug' => 'studio',
                'summary' => 'Comprehensive suite for creative studios and teams',
                'description' => 'Studio package provides comprehensive tools for creative teams, advanced rendering capabilities, studio-level collaboration features, and multi-user license management for professional studios.',
                'image' => 'packages/studio.png',
                'is_active' => true,
                'sorting' => 3,
            ],
            [
                'name' => 'Ultimate',
                'slug' => 'ultimate',
                'summary' => 'Complete solution with unlimited access to all features',
                'description' => 'Ultimate package includes all features, unlimited usage, premium support, early access to new features, enterprise-level capabilities, and dedicated account management for maximum productivity.',
                'image' => 'packages/ultimate.png',
                'is_active' => true,
                'sorting' => 4,
            ],
            [
                'name' => 'Student',
                'slug' => 'student',
                'summary' => 'Educational discount package for students and learners',
                'description' => 'Student package offers significantly discounted access to core features for verified students, educators, and educational institutions. Includes learning resources and educational project templates.',
                'image' => 'packages/student.png',
                'is_active' => true,
                'sorting' => 5,
            ],
            [
                'name' => 'Researcher',
                'slug' => 'researcher',
                'summary' => 'Specialized package for academic research and institutions',
                'description' => 'Researcher package provides specialized tools for academic research, data analysis capabilities, institutional licensing options, and academic collaboration features for research projects.',
                'image' => 'packages/researcher.png',
                'is_active' => true,
                'sorting' => 6,
            ],
            [
                'name' => 'Demo',
                'slug' => 'demo',
                'summary' => 'Limited demonstration package to explore core features',
                'description' => 'Demo package allows users to explore core functionality with limited features and watermarked output for evaluation purposes. Perfect for testing compatibility and basic workflows.',
                'image' => 'packages/demo.png',
                'is_active' => true,
                'sorting' => 7,
            ],
            [
                'name' => 'Trial',
                'slug' => 'trial',
                'summary' => 'Time-limited trial access to evaluate the full feature set',
                'description' => 'Trial package provides temporary access to all features for a limited time period, allowing users to fully evaluate the software before making a purchase decision.',
                'image' => 'packages/trial.png',
                'is_active' => true,
                'sorting' => 8,
            ],
        ];

        // Create package records
        foreach ($packageData as $data) {
            Package::create($data);
            $this->command->info("✅ Created package: {$data['name']}");
        }

        $activeCount = Package::where('is_active', true)->count();
        $totalCount = Package::count();
        
        $this->command->info("🎉 Successfully created {$totalCount} package records ({$activeCount} active, " . ($totalCount - $activeCount) . " inactive)");

        // Display package hierarchy
        $this->command->info("");
        $this->command->info("📦 Package Hierarchy:");
        $this->command->info("─────────────────────");
        Package::orderBy('sorting')->get()->each(function ($package) {
            $this->command->info("  {$package->sorting}. {$package->name} - {$package->summary}");
        });
    }
}
