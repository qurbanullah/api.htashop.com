<?php

declare(strict_types=1);

/**
 * Policy class file.
 * php version 8.4
 *
 * @category  App\Policies
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

namespace App\Policies;

use App\Models\CrashReport;
use App\Models\User;

/**
 * CrashReport Policy class.
 *
 * @category App\Policies
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class CrashReportPolicy
{
    /**
     * Determine if the user can view any crash reports.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'superadmin']);
    }

    /**
     * Determine if the user can view the crash report.
     */
    public function view(User $user, CrashReport $crashReport): bool
    {
        return $user->hasRole(['admin', 'superadmin']);
    }

    /**
     * Determine if the user can create crash reports.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'superadmin']);
    }

    /**
     * Determine if the user can update the crash report.
     */
    public function update(User $user, CrashReport $crashReport): bool
    {
        return $user->hasRole(['admin', 'superadmin']);
    }

    /**
     * Determine if the user can delete the crash report.
     */
    public function delete(User $user, CrashReport $crashReport): bool
    {
        return $user->hasRole('superadmin');
    }

    /**
     * Determine if the user can restore the crash report.
     */
    public function restore(User $user, CrashReport $crashReport): bool
    {
        return $user->hasRole('superadmin');
    }

    /**
     * Determine if the user can permanently delete the crash report.
     */
    public function forceDelete(User $user, CrashReport $crashReport): bool
    {
        return $user->hasRole('superadmin');
    }

    /**
     * Determine if the user can download crash report files.
     */
    public function download(User $user, CrashReport $crashReport): bool
    {
        return $user->hasRole(['admin', 'superadmin']);
    }

    /**
     * Determine if the user can assign crash reports.
     */
    public function assign(User $user, CrashReport $crashReport): bool
    {
        return $user->hasRole(['admin', 'superadmin']);
    }

    /**
     * Determine if the user can resolve crash reports.
     */
    public function resolve(User $user, CrashReport $crashReport): bool
    {
        return $user->hasRole(['admin', 'superadmin']);
    }
}
