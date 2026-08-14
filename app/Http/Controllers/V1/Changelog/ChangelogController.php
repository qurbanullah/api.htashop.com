<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Changelog;

use App\Http\Controllers\Controller;
use App\Models\Changelog;
use App\Models\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChangelogController extends Controller
{
    /**
     * Display a paginated list of all active changelogs (Public endpoint).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min((int) $request->input('per_page', 12), 50);
            $page = (int) $request->input('page', 1);
            $softwareSlug = $request->input('software');
            $search = $request->input('search');

            $query = Changelog::query()
                ->where('is_active', true)
                ->orderBy('release_date', 'desc')
                ->orderBy('created_at', 'desc');

            // Filter by software if provided
            if ($softwareSlug) {
                $query->whereHasMorph('changelogable', [Version::class], function ($q) use ($softwareSlug) {
                    $q->whereHas('software', function ($sq) use ($softwareSlug) {
                        $sq->where('slug', $softwareSlug);
                    });
                });
            }

            // Search in title and content
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%")
                      ->orWhere('version_number', 'like', "%{$search}%");
                });
            }

            $changelogs = $query->paginate($perPage, ['*'], 'page', $page);

            // Get version info for each changelog
            $data = $changelogs->getCollection()->map(function ($changelog) {
                $version = null;
                if ($changelog->changelogable_type === 'App\\Models\\Version') {
                    $version = Version::with('software:id,name,slug,icon')
                        ->find($changelog->changelogable_id);
                }

                return [
                    'id' => $changelog->id,
                    'title' => $changelog->title,
                    'excerpt' => $changelog->content
                        ? \Illuminate\Support\Str::limit(strip_tags($changelog->content), 150)
                        : null,
                    'content' => $changelog->content,
                    'file_path' => $changelog->file_path,
                    'file_name' => $changelog->file_name,
                    'version_number' => $changelog->version_number,
                    'release_date' => $changelog->release_date?->format('Y-m-d'),
                    'created_at' => $changelog->created_at?->toISOString(),
                    'version' => $version ? [
                        'id' => $version->id,
                        'version_number' => $version->version_number,
                        'name' => $version->name,
                        'software' => $version->software ? [
                            'id' => $version->software->id,
                            'name' => $version->software->name,
                            'slug' => $version->software->slug,
                            'icon' => $version->software->icon,
                        ] : null,
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Changelogs retrieved successfully',
                'data' => $data,
                'meta' => [
                    'current_page' => $changelogs->currentPage(),
                    'per_page' => $changelogs->perPage(),
                    'total' => $changelogs->total(),
                    'last_page' => $changelogs->lastPage(),
                    'from' => $changelogs->firstItem(),
                    'to' => $changelogs->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get changelogs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve changelogs',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified changelog (Public endpoint).
     *
     * @param int $id Changelog ID
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $changelog = Changelog::query()
                ->where('id', $id)
                ->where('is_active', true)
                ->first();

            if (!$changelog) {
                return response()->json([
                    'success' => false,
                    'message' => 'Changelog not found',
                ], 404);
            }

            // Get the associated version for additional context
            $version = null;
            if ($changelog->changelogable_type === 'App\\Models\\Version') {
                $version = Version::with('software:id,name,slug,icon')
                    ->find($changelog->changelogable_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Changelog retrieved successfully',
                'data' => [
                    'id' => $changelog->id,
                    'title' => $changelog->title,
                    'content' => $changelog->content,
                    'markdown_content' => $changelog->markdown_content,
                    'file_path' => $changelog->file_path,
                    'file_name' => $changelog->file_name,
                    'version_number' => $changelog->version_number,
                    'release_date' => $changelog->release_date?->format('Y-m-d'),
                    'is_active' => $changelog->is_active,
                    'sorting' => $changelog->sorting,
                    'created_at' => $changelog->created_at?->toISOString(),
                    'updated_at' => $changelog->updated_at?->toISOString(),
                    // Version context
                    'version' => $version ? [
                        'id' => $version->id,
                        'version_number' => $version->version_number,
                        'name' => $version->name,
                        'software' => $version->software ? [
                            'id' => $version->software->id,
                            'name' => $version->software->name,
                            'slug' => $version->software->slug,
                            'icon' => $version->software->icon,
                        ] : null,
                    ] : null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get changelog', [
                'changelog_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve changelog',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
