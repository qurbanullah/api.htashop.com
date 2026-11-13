<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Weekly internal digest of quote performance for the admin team.
 */
class QuoteReportDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $report  Output of BuildQuoteReportAction
     */
    public function __construct(
        public array $report,
        public int $days,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Quote performance — %s',
                $this->days === 0 ? 'all time' : 'last '.$this->days.' days',
            ),
        );
    }

    public function content(): Content
    {
        $kpis = $this->report['kpis'] ?? [];
        $currency = (string) ($kpis['currency'] ?? 'PKR');

        return new Content(
            view: 'emails.quote-report-digest',
            with: [
                'report' => $this->report,
                'days' => $this->days,
                'currency' => $currency,
                'pipelineValue' => (float) ($kpis['quoted_value_by_currency'][$currency] ?? 0),
                'wonValue' => (float) ($kpis['accepted_value_by_currency'][$currency] ?? 0),
                'avgFirstResponse' => $this->duration($kpis['avg_first_response_minutes'] ?? null),
                'avgDecision' => $this->duration($kpis['avg_decision_minutes'] ?? null),
                'adminUrl' => rtrim((string) config('app.admin_url'), '/').'/quotes/reports',
            ],
        );
    }

    /**
     * Short, human duration used in the KPI table.
     */
    private function duration(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        if ($minutes < 60) {
            return round($minutes).'m';
        }

        $hours = $minutes / 60;

        return $hours < 24
            ? rtrim(rtrim(number_format($hours, 1), '0'), '.').'h'
            : rtrim(rtrim(number_format($hours / 24, 1), '0'), '.').'d';
    }
}
