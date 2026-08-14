<?php

namespace Database\Factories;

use App\Models\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Feature>
 */
class FeatureFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Feature::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $features = $this->get3DSoftwareFeatures();

        return [
            'name' => '',
            'slug' => '',
            'summary' => '',
            'description' => '',
            'image' => null, // Can be populated later if needed
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'sorting' => $this->faker->numberBetween(1, 100),
        ];
    }

    /**
     * Create a sequence of all features.
     */
    public function sequence(...$args): static
    {
        $features = $this->get3DSoftwareFeatures();
        $sequences = [];

        foreach ($features as $feature) {
            $sequences[] = [
                'name' => $feature['name'],
                'slug' => Str::slug($feature['name']),
                'summary' => $feature['summary'],
                'description' => $feature['description'],
            ];
        }

        return parent::sequence(...$sequences);
    }

    /**
     * Get 3D software features for Synopsys Simpleware, Volume Graphics, etc.
     *
     * @return array
     */
    private function get3DSoftwareFeatures(): array
    {
        return [
            // Synopsys Simpleware Features
            [
                'name' => 'ScanIP Image Processing',
                'summary' => 'Advanced image processing and segmentation for medical and industrial CT/MRI data',
                'description' => 'Comprehensive image processing toolkit for converting 3D image data (CT, MRI, μCT) into high-quality models for simulation and analysis. Includes advanced segmentation algorithms, noise reduction, and image enhancement capabilities.'
            ],
            [
                'name' => 'FE Mesh Generation',
                'summary' => 'Automatic finite element mesh generation from image data',
                'description' => 'Generate high-quality tetrahedral and hexahedral finite element meshes directly from segmented image data. Optimized for complex geometries with automatic mesh refinement and quality control.'
            ],
            [
                'name' => 'CAD Export Module',
                'summary' => 'Export processed models to major CAD formats',
                'description' => 'Export segmented models and meshes to industry-standard CAD formats including STEP, IGES, STL, and PLY. Maintains geometric accuracy and supports parametric model creation.'
            ],
            [
                'name' => 'Multi-Material Support',
                'summary' => 'Handle complex multi-material structures',
                'description' => 'Advanced capabilities for processing and analyzing multi-material objects with different density regions, material interfaces, and complex internal structures.'
            ],
            [
                'name' => 'NURBS Surface Generation',
                'summary' => 'Create smooth NURBS surfaces from voxel data',
                'description' => 'Convert voxel-based segmented data into smooth NURBS surfaces suitable for high-end CAD applications and manufacturing processes.'
            ],
            [
                'name' => 'Simulation Preprocessing',
                'summary' => 'Prepare models for FEA simulation software',
                'description' => 'Specialized tools for preparing image-based models for finite element analysis in popular simulation packages like ANSYS, Abaqus, and COMSOL.'
            ],

            // Volume Graphics / VG Studio Features
            [
                'name' => 'Advanced Volume Rendering',
                'summary' => 'High-quality 3D volume visualization and rendering',
                'description' => 'State-of-the-art volume rendering engine with real-time visualization of CT/μCT data. Supports advanced lighting models, transfer functions, and interactive exploration of volumetric datasets.'
            ],
            [
                'name' => 'Defect Detection',
                'summary' => 'Automated detection and analysis of internal defects',
                'description' => 'AI-powered defect detection algorithms for identifying voids, inclusions, cracks, and other internal defects in CT scans. Includes statistical analysis and reporting capabilities.'
            ],
            [
                'name' => 'Dimensional Metrology',
                'summary' => 'Precise dimensional measurements on CT data',
                'description' => 'Advanced metrology tools for performing accurate dimensional measurements, geometric tolerancing, and quality control directly on volumetric CT data without physical sectioning.'
            ],
            [
                'name' => 'Surface Extraction',
                'summary' => 'Extract high-quality surfaces from volume data',
                'description' => 'Sophisticated algorithms for extracting accurate surface representations from volumetric data using advanced iso-surface extraction and surface optimization techniques.'
            ],
            [
                'name' => 'Multi-Resolution Analysis',
                'summary' => 'Analyze data at multiple resolution levels',
                'description' => 'Hierarchical analysis capabilities allowing users to examine volumetric data at different resolution levels, from overview to microscopic detail analysis.'
            ],
            [
                'name' => 'Fiber Analysis Module',
                'summary' => 'Specialized analysis for fiber-reinforced materials',
                'description' => 'Dedicated tools for analyzing fiber orientation, distribution, and characteristics in composite materials. Includes statistical analysis of fiber networks and orientation tensors.'
            ],
            [
                'name' => 'Porosity Analysis',
                'summary' => 'Comprehensive porosity and void analysis',
                'description' => 'Advanced algorithms for detecting, analyzing, and quantifying porosity in materials. Includes pore size distribution, connectivity analysis, and statistical reporting.'
            ],
            [
                'name' => 'ROI Management',
                'summary' => 'Region of Interest definition and management',
                'description' => 'Flexible tools for defining, managing, and analyzing specific regions of interest within large volumetric datasets. Supports multiple ROI types and batch processing.'
            ],

            // General 3D Processing Features
            [
                'name' => 'Point Cloud Processing',
                'summary' => 'Advanced point cloud manipulation and analysis',
                'description' => 'Comprehensive suite of tools for processing, filtering, and analyzing large point cloud datasets from various 3D scanning technologies.'
            ],
            [
                'name' => 'Surface Reconstruction',
                'summary' => 'Reconstruct surfaces from scattered point data',
                'description' => 'Advanced algorithms for reconstructing watertight surfaces from incomplete or noisy point cloud data using various interpolation and fitting techniques.'
            ],
            [
                'name' => 'Texture Mapping',
                'summary' => 'Apply and manage textures on 3D models',
                'description' => 'Professional texture mapping capabilities for applying photorealistic textures to 3D models with UV mapping, texture atlasing, and seamless texture projection.'
            ],
            [
                'name' => 'Mesh Optimization',
                'summary' => 'Optimize mesh topology and quality',
                'description' => 'Advanced mesh processing algorithms for reducing polygon count, improving mesh quality, and optimizing topology for specific applications like 3D printing or simulation.'
            ],
            [
                'name' => 'Animation Support',
                'summary' => 'Create and manage 3D animations',
                'description' => 'Full-featured animation system supporting keyframe animation, morphing, and dynamic simulations for creating professional 3D animations and visualizations.'
            ],
            [
                'name' => 'Scripting Interface',
                'summary' => 'Programmable automation and customization',
                'description' => 'Powerful scripting interface supporting Python, JavaScript, and proprietary scripting languages for automating workflows and creating custom analysis tools.'
            ],
            [
                'name' => 'Batch Processing',
                'summary' => 'Automated processing of multiple datasets',
                'description' => 'Efficient batch processing capabilities for handling large numbers of datasets with consistent processing parameters and automated quality control.'
            ],
            [
                'name' => 'Cloud Integration',
                'summary' => 'Cloud-based processing and collaboration',
                'description' => 'Integration with cloud platforms for scalable processing of large datasets, remote collaboration, and distributed computing capabilities.'
            ],
            [
                'name' => 'Real-time Collaboration',
                'summary' => 'Multi-user collaborative editing and review',
                'description' => 'Real-time collaboration features allowing multiple users to work on the same project simultaneously with conflict resolution and change tracking.'
            ],
            [
                'name' => 'Advanced Filtering',
                'summary' => 'Sophisticated noise reduction and filtering',
                'description' => 'State-of-the-art filtering algorithms for noise reduction, edge enhancement, and signal processing optimized for 3D volumetric and surface data.'
            ],
            [
                'name' => 'Machine Learning Integration',
                'summary' => 'AI-powered analysis and automation',
                'description' => 'Integration of machine learning algorithms for automated feature detection, classification, and predictive analysis of 3D data with customizable AI models.'
            ],
            [
                'name' => 'Multi-Scale Visualization',
                'summary' => 'Seamless visualization across different scales',
                'description' => 'Advanced visualization system supporting seamless navigation from macro to micro scales with level-of-detail optimization and adaptive rendering.'
            ],
            [
                'name' => 'Comparative Analysis',
                'summary' => 'Compare and analyze multiple datasets',
                'description' => 'Comprehensive tools for comparing multiple 3D datasets, tracking changes over time, and performing statistical analysis of variations and differences.'
            ],
            [
                'name' => 'Report Generation',
                'summary' => 'Automated analysis reporting',
                'description' => 'Professional report generation system with customizable templates, automatic chart generation, and export to various formats including PDF, Word, and HTML.'
            ],
            [
                'name' => 'GPU Acceleration',
                'summary' => 'Hardware-accelerated processing',
                'description' => 'Optimized GPU acceleration for compute-intensive operations including volume rendering, mesh processing, and numerical computations for improved performance.'
            ],
            [
                'name' => 'Virtual Reality Support',
                'summary' => 'Immersive VR visualization and interaction',
                'description' => 'Native virtual reality support for immersive visualization and interaction with 3D data using popular VR headsets and motion controllers.'
            ],
            [
                'name' => 'Quality Assurance Tools',
                'summary' => 'Comprehensive quality control and validation',
                'description' => 'Advanced quality assurance tools for validating data integrity, mesh quality, and analysis results with automated error detection and correction suggestions.'
            ],
            [
                'name' => 'Cross-Platform Compatibility',
                'summary' => 'Multi-platform support and deployment',
                'description' => 'Full compatibility across Windows, macOS, and Linux platforms with consistent feature sets and seamless data exchange between different operating systems.'
            ],
            [
                'name' => 'API Integration',
                'summary' => 'Programmable API for third-party integration',
                'description' => 'Comprehensive REST API and SDK for integrating 3D processing capabilities into third-party applications and custom workflow automation systems.'
            ],
            [
                'name' => 'Data Security',
                'summary' => 'Enterprise-grade security and encryption',
                'description' => 'Advanced security features including data encryption, secure authentication, access control, and audit logging for sensitive industrial and medical data.'
            ],
            [
                'name' => 'High-Performance Computing',
                'summary' => 'Distributed processing and cluster support',
                'description' => 'Support for high-performance computing environments with distributed processing capabilities, cluster integration, and scalable parallel computing architectures.'
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
    }

    /**
     * Indicate that the feature is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the feature is for Synopsys Simpleware.
     */
    public function simpleware(): static
    {
        $simplewareFeatures = [
            'ScanIP Image Processing',
            'FE Mesh Generation',
            'CAD Export Module',
            'Multi-Material Support',
            'NURBS Surface Generation',
            'Simulation Preprocessing'
        ];

        $featureName = $this->faker->randomElement($simplewareFeatures);

        return $this->state(fn (array $attributes) => [
            'name' => $featureName,
            'slug' => Str::slug($featureName),
        ]);
    }

    /**
     * Indicate that the feature is for Volume Graphics.
     */
    public function volumeGraphics(): static
    {
        $vgFeatures = [
            'Advanced Volume Rendering',
            'Defect Detection',
            'Dimensional Metrology',
            'Surface Extraction',
            'Multi-Resolution Analysis',
            'Fiber Analysis Module',
            'Porosity Analysis',
            'ROI Management'
        ];

        $featureName = $this->faker->randomElement($vgFeatures);

        return $this->state(fn (array $attributes) => [
            'name' => $featureName,
            'slug' => Str::slug($featureName),
        ]);
    }
}
