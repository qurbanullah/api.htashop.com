<?php

declare(strict_types=1);

/**
 * Feature and Module Assignment Service
 * php version 8.4
 *
 * @category  App\Services\Features
 *
 * @author    Qurban Ullah <qurbanullah@gmail.com>
 * @copyright 2024 Qurban Ullah - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Qurban Ullah <qurbanullah@gmail.com>, 2024
 * @license   CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @version   GIT: <git_id>
 *
 * @link      https://github.com/qurbanullah
 */

namespace App\Services\Features;

use App\Models\Feature;
use App\Models\License;
use App\Models\Module;
use App\Models\Package;
use Illuminate\Support\Collection;

/**
 * Service for managing Feature and Module assignments to Licenses and Packages.
 *
 * @category App\Services\Features
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class FeatureModuleAssignmentService
{
    /**
     * Assign features to a license.
     * Only works if the package is 'custom' type.
     *
     * @param License $license
     * @param array $featureIds
     * @return bool
     * @throws \Exception
     */
    public function assignFeaturesToLicense(License $license, array $featureIds): bool
    {
        if (!$license->canHaveCustomFeatures()) {
            throw new \Exception('Features can only be assigned to licenses with custom packages.');
        }

        $license->features()->sync($featureIds);

        return true;
    }

    /**
     * Assign modules to a license.
     * Only works if the package is 'custom' type.
     *
     * @param License $license
     * @param array $moduleIds
     * @return bool
     * @throws \Exception
     */
    public function assignModulesToLicense(License $license, array $moduleIds): bool
    {
        if (!$license->canHaveCustomFeatures()) {
            throw new \Exception('Modules can only be assigned to licenses with custom packages.');
        }

        $license->modules()->sync($moduleIds);

        return true;
    }

    /**
     * Assign features to a package.
     *
     * @param Package $package
     * @param array $featureIds
     * @return bool
     */
    public function assignFeaturesToPackage(Package $package, array $featureIds): bool
    {
        $package->features()->sync($featureIds);

        return true;
    }

    /**
     * Assign modules to a package.
     *
     * @param Package $package
     * @param array $moduleIds
     * @return bool
     */
    public function assignModulesToPackage(Package $package, array $moduleIds): bool
    {
        $package->modules()->sync($moduleIds);

        return true;
    }

    /**
     * Get all available features.
     *
     * @param bool $activeOnly
     * @return Collection
     */
    public function getAvailableFeatures(bool $activeOnly = true): Collection
    {
        $query = Feature::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sorting')->orderBy('name')->get();
    }

    /**
     * Get all available modules.
     *
     * @param bool $activeOnly
     * @return Collection
     */
    public function getAvailableModules(bool $activeOnly = true): Collection
    {
        $query = Module::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sorting')->orderBy('name')->get();
    }

    /**
     * Get features assigned to a license.
     *
     * @param License $license
     * @return Collection
     */
    public function getLicenseFeatures(License $license): Collection
    {
        return $license->features;
    }

    /**
     * Get modules assigned to a license.
     *
     * @param License $license
     * @return Collection
     */
    public function getLicenseModules(License $license): Collection
    {
        return $license->modules;
    }

    /**
     * Get features assigned to a package.
     *
     * @param Package $package
     * @return Collection
     */
    public function getPackageFeatures(Package $package): Collection
    {
        return $package->features;
    }

    /**
     * Get modules assigned to a package.
     *
     * @param Package $package
     * @return Collection
     */
    public function getPackageModules(Package $package): Collection
    {
        return $package->modules;
    }

    /**
     * Check if a license can have custom features/modules assigned.
     *
     * @param License $license
     * @return bool
     */
    public function canLicenseHaveCustomFeatures(License $license): bool
    {
        return $license->canHaveCustomFeatures();
    }

    /**
     * Get licenses that use custom packages.
     *
     * @return Collection
     */
    public function getCustomPackageLicenses(): Collection
    {
        return License::whereHas('package', function ($query) {
            $query->where('type', 'custom');
        })->with(['package', 'features', 'modules'])->get();
    }

    /**
     * Get all custom packages.
     *
     * @return Collection
     */
    public function getCustomPackages(): Collection
    {
        return Package::where('type', 'custom')
            ->with(['features', 'modules'])
            ->orderBy('sorting')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get statistics about feature/module assignments.
     *
     * @return array
     */
    public function getAssignmentStatistics(): array
    {
        return [
            'total_features' => Feature::count(),
            'active_features' => Feature::where('is_active', true)->count(),
            'total_modules' => Module::count(),
            'active_modules' => Module::where('is_active', true)->count(),
            'custom_packages' => Package::where('type', 'custom')->count(),
            'standard_packages' => Package::where('type', 'standard')->count(),
            'licenses_with_custom_features' => License::whereHas('package', function ($query) {
                $query->where('type', 'custom');
            })->whereHas('features')->count(),
            'licenses_with_custom_modules' => License::whereHas('package', function ($query) {
                $query->where('type', 'custom');
            })->whereHas('modules')->count(),
        ];
    }
}
