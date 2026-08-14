<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing tags
        Tag::query()->delete();

        // Define all unique tags for 3D software
        $tags = [
            ['name' => 'Medical Imaging', 'summary' => 'Advanced medical imaging and diagnostic analysis', 'description' => 'Tag for medical imaging applications including CT, MRI, ultrasound, and other diagnostic imaging modalities used in healthcare and medical research.'],
            ['name' => 'Industrial CT', 'summary' => 'Industrial computed tomography and non-destructive testing', 'description' => 'Tag for industrial CT scanning applications used in manufacturing, quality control, and non-destructive testing of components and materials.'],
            ['name' => 'Finite Element Analysis', 'summary' => 'FEA simulation and structural analysis workflows', 'description' => 'Tag for finite element analysis applications including mesh generation, simulation setup, and structural mechanics analysis.'],
            ['name' => 'Image Segmentation', 'summary' => 'Advanced image segmentation and object detection', 'description' => 'Tag for image segmentation techniques and algorithms used to identify and isolate structures within 3D imaging data.'],
            ['name' => 'Biomedical Research', 'summary' => 'Biomedical and life sciences research applications', 'description' => 'Tag for biomedical research applications including tissue analysis, organ modeling, and biological structure characterization.'],
            ['name' => 'Quality Control', 'summary' => 'Industrial quality control and inspection', 'description' => 'Tag for quality control applications including defect detection, dimensional measurement, and compliance verification in manufacturing.'],
            ['name' => 'Defect Analysis', 'summary' => 'Automated defect detection and classification', 'description' => 'Tag for defect analysis workflows including porosity detection, crack analysis, and inclusion identification in materials and components.'],
            ['name' => 'Metrology', 'summary' => 'Precision measurement and dimensional analysis', 'description' => 'Tag for metrology applications including dimensional measurement, geometric tolerancing, and comparative analysis against CAD models.'],
            ['name' => 'Volume Rendering', 'summary' => 'Advanced 3D volume visualization techniques', 'description' => 'Tag for volume rendering applications providing real-time visualization of 3D datasets with advanced lighting and transparency effects.'],
            ['name' => 'Materials Science', 'summary' => 'Materials characterization and analysis', 'description' => 'Tag for materials science applications including material property analysis, composition studies, and microstructure characterization.'],
            ['name' => 'Machine Learning', 'summary' => 'AI and machine learning integration', 'description' => 'Tag for machine learning applications including automated analysis, pattern recognition, and predictive modeling in 3D imaging workflows.'],
            ['name' => 'Cloud Computing', 'summary' => 'Cloud-based processing and collaboration', 'description' => 'Tag for cloud computing applications enabling distributed processing, remote collaboration, and scalable computational resources.'],
            ['name' => 'Batch Processing', 'summary' => 'Automated batch processing workflows', 'description' => 'Tag for batch processing applications enabling high-throughput analysis of large datasets with consistent processing parameters.'],
            ['name' => 'Virtual Reality', 'summary' => 'Immersive VR visualization and interaction', 'description' => 'Tag for virtual reality applications providing immersive exploration and collaborative review of 3D datasets in virtual environments.'],
            ['name' => 'API Integration', 'summary' => 'Third-party integration and custom development', 'description' => 'Tag for API integration applications enabling custom software development, third-party connections, and automated workflow creation.'],
            ['name' => 'Point Cloud', 'summary' => 'Point cloud processing and analysis', 'description' => 'Tag for point cloud applications including laser scanning data processing, photogrammetry, and 3D reconstruction workflows.'],
            ['name' => 'Aerospace', 'summary' => 'Aerospace and aviation industry applications', 'description' => 'Tag for aerospace applications including aircraft component analysis, turbine blade inspection, and aerospace materials testing.'],
            ['name' => 'Automotive', 'summary' => 'Automotive industry and manufacturing', 'description' => 'Tag for automotive applications including engine component analysis, crash test simulation, and automotive parts quality control.'],
            ['name' => 'Security', 'summary' => 'Data security and access control', 'description' => 'Tag for security applications including data encryption, user authentication, and compliance with industry security standards.'],
            ['name' => 'Workflow Automation', 'summary' => 'Automated processing and scripting', 'description' => 'Tag for workflow automation including script-based processing, macro development, and custom analysis pipeline creation.'],
            ['name' => 'Multi-Physics', 'summary' => 'Multi-physics simulation and analysis', 'description' => 'Tag for multi-physics applications including coupled simulations, heat transfer analysis, and fluid-structure interaction studies.'],
            ['name' => 'Collaboration', 'summary' => 'Team collaboration and project management', 'description' => 'Tag for collaboration applications including team-based workflows, shared annotations, and distributed project management.'],
            ['name' => 'Mobile Access', 'summary' => 'Mobile device access and remote monitoring', 'description' => 'Tag for mobile applications including tablet interfaces, smartphone access, and remote monitoring of processing jobs.'],
            ['name' => 'Research', 'summary' => 'Academic and scientific research', 'description' => 'Tag for research applications including academic studies, scientific analysis, and experimental data processing.'],
            ['name' => 'Performance', 'summary' => 'High-performance computing and optimization', 'description' => 'Tag for performance optimization including GPU acceleration, parallel processing, and computational efficiency improvements.'],
        ];

        // Create each tag individually to ensure uniqueness
        foreach ($tags as $index => $tagData) {
            Tag::create([
                'name' => $tagData['name'],
                'slug' => Str::slug($tagData['name']),
                'summary' => $tagData['summary'],
                'description' => $tagData['description'],
                'image' => fake()->optional(0.4)->imageUrl(300, 200, 'technology', true, 'tag'),
                'is_active' => fake()->boolean(95),
                'sorting' => $index + 1, // Sequential sorting to maintain order
            ]);
        }

        $this->command->info('Tags seeded successfully!');
        $this->command->info('Created ' . Tag::count() . ' tags total');
        $this->command->info('Active tags: ' . Tag::where('is_active', true)->count());
        $this->command->info('Inactive tags: ' . Tag::where('is_active', false)->count());
    }
}
