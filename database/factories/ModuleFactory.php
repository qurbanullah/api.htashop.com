<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Module>
 */
class ModuleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Module::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $modules = [
            // Synopsys Simpleware Modules
            [
                'name' => 'ScanIP Image Processing',
                'summary' => 'Advanced image processing and segmentation tools for medical and industrial imaging',
                'description' => 'Comprehensive image processing module featuring advanced segmentation algorithms, noise reduction, and image enhancement tools specifically designed for medical CT, MRI, and industrial μCT data.',
            ],
            [
                'name' => 'FE Mesh Generation',
                'summary' => 'Automatic finite element mesh generation for complex 3D geometries',
                'description' => 'Robust mesh generation module that creates high-quality finite element meshes from segmented image data, supporting tetrahedral, hexahedral, and hybrid mesh types for simulation workflows.',
            ],
            [
                'name' => 'CAD Import/Export',
                'summary' => 'Seamless integration with major CAD formats and platforms',
                'description' => 'Universal CAD connectivity module supporting import and export of STEP, IGES, STL, PLY, and other standard CAD formats for integration with design and manufacturing workflows.',
            ],
            [
                'name' => 'Multi-Phase Simulation',
                'summary' => 'Advanced simulation capabilities for multi-material and multi-phase analysis',
                'description' => 'Sophisticated simulation module enabling complex multi-phase flow analysis, heat transfer, and structural mechanics simulations on realistic geometries derived from imaging data.',
            ],
            [
                'name' => 'Morphometric Analysis',
                'summary' => 'Quantitative analysis and measurement tools for 3D structures',
                'description' => 'Comprehensive morphometric analysis module providing volume measurements, surface area calculations, porosity analysis, and statistical shape analysis for research and quality control.',
            ],
            [
                'name' => 'Batch Processing Engine',
                'summary' => 'Automated processing workflows for high-throughput analysis',
                'description' => 'Powerful batch processing module that automates repetitive tasks, enables scripting workflows, and supports high-throughput processing of large datasets with consistent quality.',
            ],

            // Volume Graphics / VG Studio Modules
            [
                'name' => 'Advanced Volume Rendering',
                'summary' => 'High-performance volume visualization with real-time rendering',
                'description' => 'State-of-the-art volume rendering module providing real-time visualization of large 3D datasets with advanced lighting, transparency, and multi-volume rendering capabilities.',
            ],
            [
                'name' => 'Defect Analysis Suite',
                'summary' => 'Comprehensive defect detection and analysis for industrial CT',
                'description' => 'Specialized module for automated defect detection, classification, and analysis in industrial CT scans, featuring porosity analysis, crack detection, and inclusion identification.',
            ],
            [
                'name' => 'Dimensional Metrology',
                'summary' => 'Precision measurement and geometric dimensioning tools',
                'description' => 'High-precision metrology module for dimensional measurements, geometric tolerancing, and comparative analysis between CT data and CAD models for quality assurance.',
            ],
            [
                'name' => 'Surface Determination',
                'summary' => 'Advanced surface extraction and mesh generation algorithms',
                'description' => 'Sophisticated surface extraction module using advanced algorithms to generate accurate surface meshes from volume data, supporting various surface detection methods and mesh optimization.',
            ],
            [
                'name' => 'Multi-Material Analysis',
                'summary' => 'Material identification and analysis for composite structures',
                'description' => 'Advanced material analysis module capable of identifying and analyzing multiple materials within complex assemblies, supporting material property mapping and composition analysis.',
            ],
            [
                'name' => 'Report Generation',
                'summary' => 'Automated report creation with customizable templates',
                'description' => 'Comprehensive reporting module that generates professional analysis reports with customizable templates, supporting PDF export, measurement tables, and 3D visualization integration.',
            ],

            // General 3D Software Modules
            [
                'name' => 'Point Cloud Processing',
                'summary' => 'Advanced tools for laser scanning and photogrammetry data',
                'description' => 'Comprehensive point cloud processing module supporting registration, filtering, mesh reconstruction, and analysis of large-scale laser scanning and photogrammetry datasets.',
            ],
            [
                'name' => 'Neural Network Integration',
                'summary' => 'AI-powered analysis and automated feature recognition',
                'description' => 'Machine learning module integrating neural networks for automated feature recognition, image segmentation, and predictive analysis in 3D imaging workflows.',
            ],
            [
                'name' => 'Virtual Reality Viewer',
                'summary' => 'Immersive 3D visualization and collaboration platform',
                'description' => 'Virtual reality module enabling immersive exploration of 3D datasets, collaborative review sessions, and intuitive interaction with complex 3D structures in virtual environments.',
            ],
            [
                'name' => 'Cloud Computing Interface',
                'summary' => 'Scalable cloud processing for large-scale computations',
                'description' => 'Cloud integration module providing access to high-performance computing resources for intensive processing tasks, supporting distributed computing and large dataset analysis.',
            ],
            [
                'name' => 'API Framework',
                'summary' => 'Developer tools and API access for custom integrations',
                'description' => 'Comprehensive API framework enabling custom application development, third-party integrations, and automated workflow creation with extensive documentation and SDK support.',
            ],
            [
                'name' => 'Quality Assurance Suite',
                'summary' => 'Comprehensive quality control and validation tools',
                'description' => 'Quality assurance module providing validation tools, statistical process control, and compliance checking for regulated industries including aerospace and medical device manufacturing.',
            ],
            [
                'name' => 'Image Registration',
                'summary' => 'Multi-modal image alignment and fusion capabilities',
                'description' => 'Advanced registration module supporting alignment of multi-modal imaging data, temporal analysis, and fusion of different imaging modalities for comprehensive analysis.',
            ],
            [
                'name' => 'Simulation Coupling',
                'summary' => 'Integration with external simulation and analysis software',
                'description' => 'Simulation coupling module providing seamless integration with external FEA, CFD, and specialized simulation software for comprehensive multi-physics analysis workflows.',
            ],
            [
                'name' => 'Database Connectivity',
                'summary' => 'Enterprise database integration and data management',
                'description' => 'Database integration module supporting connection to enterprise databases, data versioning, and metadata management for large-scale industrial and research applications.',
            ],
            [
                'name' => 'Advanced Scripting',
                'summary' => 'Python and scripting interface for workflow automation',
                'description' => 'Scripting module providing Python API access, macro recording, and custom script development capabilities for advanced workflow automation and custom analysis development.',
            ],
            [
                'name' => 'Security Framework',
                'summary' => 'Enterprise-grade security and access control system',
                'description' => 'Comprehensive security module featuring role-based access control, data encryption, audit logging, and compliance features for enterprise and regulated industry deployments.',
            ],
            [
                'name' => 'Performance Optimization',
                'summary' => 'GPU acceleration and multi-threading optimization tools',
                'description' => 'Performance optimization module leveraging GPU acceleration, multi-threading, and memory optimization techniques to maximize processing speed and handle large datasets efficiently.',
            ],
            [
                'name' => 'Collaboration Tools',
                'summary' => 'Team collaboration and project management features',
                'description' => 'Collaboration module enabling team-based project management, shared annotations, version control, and real-time collaboration tools for distributed engineering and research teams.',
            ],
            [
                'name' => 'Mobile Interface',
                'summary' => 'Mobile access and remote monitoring capabilities',
                'description' => 'Mobile interface module providing tablet and smartphone access to key functionality, remote monitoring of processing jobs, and mobile visualization for field work and remote collaboration.',
            ],
            [
                'name' => 'AI Segmentation',
                'summary' => 'Intelligent automated segmentation using artificial intelligence algorithms',
                'description' => 'Advanced AI-powered segmentation module utilizing deep learning and machine learning algorithms for automated identification and classification of structures in 3D imaging data, reducing manual intervention and improving segmentation accuracy.',
            ],
            [
                'name' => 'CLI Workflow Editor',
                'summary' => 'Command-line interface for advanced workflow automation and scripting',
                'description' => 'Powerful command-line workflow editor enabling users to create, modify, and execute complex processing workflows through script-based automation, batch processing commands, and integration with external tools and systems.',
            ],
        ];

        // Use faker unique() to ensure no duplicate slugs
        $module = $this->faker->unique()->randomElement($modules);
        $name = $module['name'];
        $slug = Str::slug($name);

        return [
            'name' => $name,
            'slug' => $slug,
            'summary' => $module['summary'],
            'description' => $module['description'],
            'image' => $this->faker->optional(0.6)->imageUrl(400, 300, 'technology', true, 'module'),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'sorting' => $this->faker->numberBetween(1, 255), // unsignedTinyInteger range
        ];
    }

    /**
     * Indicate that the module is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the module is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
