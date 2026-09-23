<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send posts that are due (status = scheduled, scheduled_at <= now).
Schedule::command('post:send-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

// Regenerate the storefront sitemaps daily so crawler requests are always served
// from a warm cache (robots.txt + pages, products, posts and categories).
Schedule::command('sitemap:generate')
    ->dailyAt('02:30')
    ->withoutOverlapping();

// Refresh draft knowledge entries from published content (posts, tutorials).
// Entries are created as drafts and must be published in the admin portal.
Schedule::command('knowledge:ingest')
    ->dailyAt('03:15')
    ->withoutOverlapping();
