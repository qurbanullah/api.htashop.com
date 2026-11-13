<?php

namespace App\Console\Commands;

use App\Actions\Quotes\BuildQuoteReportAction;
use App\Helpers\AdminHelper;
use App\Mail\QuoteReportDigestMail;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class QuoteReportDigestCommand extends Command
{
    protected $signature = 'quotes:report-digest
        {--days= : Report window in days (0 = all time)}
        {--to=* : Explicit recipients; repeat the flag or comma-separate}
        {--dry-run : Show recipients and headline numbers without sending}';

    protected $description = 'Email the quote performance digest to administrators';

    public function handle(BuildQuoteReportAction $reportAction): int
    {
        $days = $this->option('days') !== null
            ? max(0, (int) $this->option('days'))
            : (int) config('quotes.digest.days', 30);

        $report = $reportAction->execute($days);
        $recipients = $this->recipients();

        if ($recipients->isEmpty()) {
            $this->warn('No recipients: no admins found and QUOTE_DIGEST_RECIPIENTS is empty.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d quote(s) in the report; %d recipient(s).',
            $report['kpis']['total'],
            $recipients->count(),
        ));

        if ($this->option('dry-run')) {
            foreach ($recipients as $email) {
                $this->line(' · '.$email);
            }

            return self::SUCCESS;
        }

        foreach ($recipients as $email) {
            Mail::to($email)->queue(new QuoteReportDigestMail($report, $days));
        }

        $this->info('Digest queued.');

        return self::SUCCESS;
    }

    /**
     * Recipients, in priority order: explicit --to, configured list, admins.
     *
     * @return Collection<int, string>
     */
    private function recipients(): Collection
    {
        $explicit = collect($this->option('to') ?? [])
            ->flatMap(fn ($value): array => explode(',', (string) $value))
            ->map(fn ($email): string => trim((string) $email))
            ->filter();

        if ($explicit->isNotEmpty()) {
            return $explicit->unique()->values();
        }

        $recipients = collect(config('quotes.digest.recipients', []))
            ->merge($this->adminEmails());

        if ($recipients->isEmpty()) {
            $recipients->push(config('quotes.notifications.fallback_email'));
        }

        return $recipients
            ->map(fn ($email): string => trim((string) $email))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Admin lookup throws when the role table is not seeded yet; the digest
     * must never fail because of it.
     *
     * @return Collection<int, string>
     */
    private function adminEmails(): Collection
    {
        try {
            return AdminHelper::getAdminEmails();
        } catch (Throwable $e) {
            Log::warning('Quote digest could not resolve admin recipients', [
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }
}
