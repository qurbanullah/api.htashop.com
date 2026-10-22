<?php

namespace App\Http\Controllers\V1\Punchout;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Collects Content-Security-Policy violation reports from the punchout SPA.
 *
 * The endpoint is unauthenticated by design: browsers send CSP reports without
 * credentials. To keep it from becoming a log-injection sink it accepts a
 * bounded payload, rate-limits per IP, and only records reports whose
 * document-uri belongs to the punchout origin.
 */
class PunchoutCspReportController extends Controller
{
    private const MAX_BODY_BYTES = 8192;

    private const MAX_REPORTS = 10;

    private const MAX_FIELD_LENGTH = 255;

    public function store(Request $request): Response
    {
        $body = (string) $request->getContent();

        if (strlen($body) > self::MAX_BODY_BYTES) {
            return response()->noContent();
        }

        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            return response()->noContent();
        }

        foreach ($this->reports($payload) as $report) {
            $this->record($report);
        }

        return response()->noContent();
    }

    /**
     * Accept both the legacy `application/csp-report` object and the Reporting
     * API array of `{type, body}` entries.
     *
     * @param  array<mixed>  $payload
     * @return list<array<mixed>>
     */
    private function reports(array $payload): array
    {
        if (isset($payload['csp-report']) && is_array($payload['csp-report'])) {
            return [$payload['csp-report']];
        }

        $reports = [];

        foreach ($payload as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (($entry['type'] ?? null) === 'csp-violation' && is_array($entry['body'] ?? null)) {
                $reports[] = $entry['body'];
            }

            if (count($reports) >= self::MAX_REPORTS) {
                break;
            }
        }

        return $reports;
    }

    /**
     * @param  array<mixed>  $report
     */
    private function record(array $report): void
    {
        $documentUri = $this->field($report, ['document-uri', 'documentURL']);

        if (! $this->belongsToPunchout($documentUri)) {
            return;
        }

        Log::warning('punchout.csp_violation', [
            // Drop the query string: a violation during handoff could otherwise
            // write the one-time `code` into the log.
            'document_uri' => $this->withoutQuery($documentUri),
            'violated_directive' => $this->field($report, ['violated-directive', 'effectiveDirective']),
            'blocked_uri' => $this->field($report, ['blocked-uri', 'blockedURL']),
            'source_file' => $this->field($report, ['source-file', 'sourceFile']),
            'line_number' => $this->field($report, ['line-number', 'lineNumber']),
            'disposition' => $this->field($report, ['disposition']),
        ]);
    }

    /**
     * @param  array<mixed>  $report
     * @param  list<string>  $keys
     */
    private function field(array $report, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! isset($report[$key]) || ! is_scalar($report[$key])) {
                continue;
            }

            return mb_substr((string) $report[$key], 0, self::MAX_FIELD_LENGTH);
        }

        return null;
    }

    private function belongsToPunchout(?string $documentUri): bool
    {
        if ($documentUri === null) {
            return false;
        }

        $host = parse_url($documentUri, PHP_URL_HOST);
        $allowed = parse_url((string) config('punchout.app_url'), PHP_URL_HOST);

        if (! is_string($host) || ! is_string($allowed)) {
            return false;
        }

        $host = strtolower($host);
        $allowed = strtolower($allowed);

        return $host === $allowed || $host === 'www.'.$allowed;
    }

    private function withoutQuery(?string $documentUri): ?string
    {
        if ($documentUri === null) {
            return null;
        }

        $parts = parse_url($documentUri);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $documentUri;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port.($parts['path'] ?? '/');
    }
}
