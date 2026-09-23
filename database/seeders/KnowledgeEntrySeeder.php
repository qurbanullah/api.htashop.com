<?php

namespace Database\Seeders;

use App\Enums\KnowledgeSourceEnum;
use App\Enums\KnowledgeStatusEnum;
use App\Models\KnowledgeEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the fallback knowledge base from config/knowledge.php.
 *
 * Idempotent (keyed by slug), so it is safe to re-run after editing the seed
 * list. Real content should be curated in the admin portal.
 */
class KnowledgeEntrySeeder extends Seeder
{
    public function run(): void
    {
        $entries = (array) config('knowledge.seed', []);
        $count = 0;

        foreach ($entries as $entry) {
            $title = (string) ($entry['title'] ?? '');

            if ($title === '' || empty($entry['body'])) {
                continue;
            }

            KnowledgeEntry::updateOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'tenant_id' => null,
                    'locale' => '*',
                    'title' => $title,
                    'question' => $entry['question'] ?? null,
                    'body' => (string) $entry['body'],
                    'tags' => $entry['tags'] ?? [],
                    'source_type' => KnowledgeSourceEnum::MANUAL->value,
                    'source_url' => $entry['source_url'] ?? null,
                    'status' => KnowledgeStatusEnum::PUBLISHED->value,
                    'priority' => (int) ($entry['priority'] ?? 0),
                    'restricted' => (bool) ($entry['restricted'] ?? false),
                    'published_at' => now(),
                ]
            );

            $count++;
        }

        $this->command?->info("Seeded {$count} knowledge entries.");
    }
}
