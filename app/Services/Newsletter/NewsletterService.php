<?php

namespace App\Services\Newsletter;

use App\Helpers\CacheHelper;
use App\Models\Newsletter;
use App\Actions\Newsletter\CreateNewsletterAction;
use App\Actions\Newsletter\UpdateNewsletterAction;
use App\Actions\Newsletter\DeleteNewsletterAction;
use App\Actions\Newsletter\SendNewsletterAction;
use App\Actions\Newsletter\ScheduleNewsletterAction;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class NewsletterService
{
    protected $createAction;
    protected $updateAction;
    protected $deleteAction;
    protected $sendAction;
    protected $scheduleAction;

    public function __construct(
        CreateNewsletterAction $createAction,
        UpdateNewsletterAction $updateAction,
        DeleteNewsletterAction $deleteAction,
        SendNewsletterAction $sendAction,
        ScheduleNewsletterAction $scheduleAction
    ) {
        $this->createAction = $createAction;
        $this->updateAction = $updateAction;
        $this->deleteAction = $deleteAction;
        $this->sendAction = $sendAction;
        $this->scheduleAction = $scheduleAction;
    }

    public function getAllNewsletters(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $cacheKey = 'newsletters_' . md5(serialize($filters)) . '_page_' . request('page', 1) . '_per_page_' . $perPage;

        return CacheHelper::remember([], $cacheKey, 300, function () use ($filters, $perPage) {
            $query = Newsletter::with('creator')->latest();

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['type'])) {
                $query->ofType($filters['type']);
            }

            if (isset($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('title', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('excerpt', 'like', '%' . $filters['search'] . '%');
                });
            }

            if (isset($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            return $query->paginate($perPage);
        });
    }

    public function getPublicNewsletters(int $perPage = 10): LengthAwarePaginator
    {
        $cacheKey = 'public_newsletters_page_' . request('page', 1) . '_per_page_' . $perPage;

        return CacheHelper::remember([], $cacheKey, 600, function () use ($perPage) {
            return Newsletter::published()
                ->where('is_published_as_blog', true)
                ->with('creator')
                ->latest('created_at')
                ->paginate($perPage);
        });
    }

    public function getNewsletterByUuid(string $uuid): ?Newsletter
    {
        $cacheKey = 'newsletter_uuid_' . $uuid;

        return CacheHelper::remember([], $cacheKey, 3600, function () use ($uuid) {
            return Newsletter::where('uuid', $uuid)
                ->with(['creator', 'tags', 'categories'])
                ->first();
        });
    }

    public function getNewsletterBySlug(string $slug): ?Newsletter
    {
        $cacheKey = 'newsletter_slug_' . $slug;

        return CacheHelper::remember([], $cacheKey, 3600, function () use ($slug) {
            return Newsletter::where('slug', $slug)
                ->where('is_published_as_blog', true)
                ->where('status', 'published')
                ->with(['creator', 'tags', 'categories'])
                ->first();
        });
    }

    public function createNewsletter(array $data, ?int $createdBy = null): Newsletter
    {
        // Handle authentication - use provided user ID or current auth user
        if (!isset($data['created_by'])) {
            $data['created_by'] = $createdBy ?? auth()->id();
        }

        $newsletter = $this->createAction->handle($data);
        $this->clearNewsletterCache();
        return $newsletter;
    }

    public function updateNewsletter(Newsletter $newsletter, array $data): Newsletter
    {
        $updatedNewsletter = $this->updateAction->handle($newsletter, $data);
        $this->clearNewsletterCache($newsletter);
        return $updatedNewsletter;
    }

    public function deleteNewsletter(Newsletter $newsletter): bool
    {
        $result = $this->deleteAction->execute($newsletter);
        $this->clearNewsletterCache($newsletter);
        return $result;
    }

    public function sendNewsletter(Newsletter $newsletter, array $options = []): array
    {
        $result = $this->sendAction->execute($newsletter, $options);
        $this->clearNewsletterCache($newsletter);
        return $result;
    }

    public function scheduleNewsletter(Newsletter $newsletter, Carbon $scheduledAt): Newsletter
    {
        $updatedNewsletter = $this->scheduleAction->execute($newsletter, $scheduledAt);
        $this->clearNewsletterCache($newsletter);
        return $updatedNewsletter;
    }

    public function publishAsBlog(Newsletter $newsletter, bool $publish = true): Newsletter
    {
        $newsletter->update([
            'is_published_as_blog' => $publish,
            'status' => $publish ? 'published' : 'draft'
        ]);

        $this->clearNewsletterCache($newsletter);
        return $newsletter->fresh();
    }

    /**
     * @deprecated Use publishAsBlog() instead. Left for backward compatibility.
     */
    public function publishAsBlolg(Newsletter $newsletter, bool $publish = true): Newsletter
    {
        return $this->publishAsBlog($newsletter, $publish);
    }

    public function getScheduledNewsletters(): \Illuminate\Database\Eloquent\Collection
    {
        return CacheHelper::remember([], 'scheduled_newsletters', 60, function () {
            return Newsletter::readyToSend()->get();
        });
    }

    public function getNewsletterStats(): array
    {
        return CacheHelper::remember([], 'newsletter_stats', 300, function () {
            return [
                'total' => Newsletter::count(),
                'drafts' => Newsletter::draft()->count(),
                'scheduled' => Newsletter::scheduled()->count(),
                'published' => Newsletter::published()->count(),
                'sent' => Newsletter::sent()->count(),
                'total_sent_count' => Newsletter::sum('sent_count'),
                'total_opened_count' => Newsletter::sum('opened_count'),
                'total_clicked_count' => Newsletter::sum('clicked_count'),
                'average_open_rate' => $this->calculateAverageOpenRate(),
                'average_click_rate' => $this->calculateAverageClickRate(),
            ];
        });
    }

    public function searchNewsletters(string $query, int $perPage = 10): LengthAwarePaginator
    {
        $cacheKey = 'search_newsletters_' . md5($query) . '_page_' . request('page', 1) . '_per_page_' . $perPage;

        return CacheHelper::remember([], $cacheKey, 300, function () use ($query, $perPage) {
            return Newsletter::where('title', 'like', '%' . $query . '%')
                ->orWhere('excerpt', 'like', '%' . $query . '%')
                ->orWhere('content', 'like', '%' . $query . '%')
                ->with('creator')
                ->latest()
                ->paginate($perPage);
        });
    }

    protected function calculateAverageOpenRate(): float
    {
        $newsletters = Newsletter::sent()->where('sent_count', '>', 0)->get();

        if ($newsletters->isEmpty()) {
            return 0.0;
        }

        $totalRate = $newsletters->sum(function ($newsletter) {
            return $newsletter->open_rate;
        });

        return round($totalRate / $newsletters->count(), 2);
    }

    protected function calculateAverageClickRate(): float
    {
        $newsletters = Newsletter::sent()->where('opened_count', '>', 0)->get();

        if ($newsletters->isEmpty()) {
            return 0.0;
        }

        $totalRate = $newsletters->sum(function ($newsletter) {
            return $newsletter->click_rate;
        });

        return round($totalRate / $newsletters->count(), 2);
    }

    protected function clearNewsletterCache(?Newsletter $newsletter = null): void
    {
        // Clear general newsletter caches
        CacheHelper::forget([], 'newsletter_stats');
        CacheHelper::forget([], 'scheduled_newsletters');
        Cache::flush(); // Clear all cache for simplicity - in production, be more selective

        // Clear specific newsletter caches if newsletter provided
        if ($newsletter) {
            CacheHelper::forget([], 'newsletter_uuid_' . $newsletter->uuid);
            CacheHelper::forget([], 'newsletter_slug_' . $newsletter->slug);
        }
    }

    /**
     * Get publishing trends for the last N days
     */
    public function getPublishingTrends(int $days = 30): array
    {
        $cacheKey = 'newsletter_publishing_trends_' . $days;

        return CacheHelper::remember([], $cacheKey, 3600, function () use ($days) {
            $endDate = now()->endOfDay();
            $startDate = now()->subDays($days - 1)->startOfDay();

            $trends = [];

            for ($i = 0; $i < $days; $i++) {
                $date = $startDate->copy()->addDays($i);
                $count = Newsletter::whereDate('created_at', $date->toDateString())->count();

                $trends[] = [
                    'date' => $date->format('M j'), // Format like "Jan 8", "Feb 5"
                    'newsletters' => $count,
                    'full_date' => $date->toDateString(),
                ];
            }

            return $trends;
        });
    }
}
