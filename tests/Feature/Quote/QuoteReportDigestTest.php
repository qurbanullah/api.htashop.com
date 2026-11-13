<?php

namespace Tests\Feature\Quote;

use App\Actions\Quotes\BuildQuoteReportAction;
use App\Mail\QuoteReportDigestMail;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

function digestAdmin(string $email = 'admin@example.test'): User
{
    // AdminHelper resolves both roles, so mirror the production seeder.
    Role::findOrCreate('super-admin', 'api');
    $role = Role::findOrCreate('admin', 'api');
    $user = User::factory()->create(['email' => $email]);
    $user->assignRole($role);

    return $user;
}

function digestQuote(array $attributes = []): QuoteRequest
{
    return QuoteRequest::query()->create(array_merge([
        'first_name' => 'Ayesha',
        'last_name' => 'Khan',
        'email' => 'ayesha@example.test',
        'organization' => 'Buyer Co',
        'job_title' => 'Buyer',
        'country' => 'Pakistan',
        'city' => 'Lahore',
        'postal_code' => '54000',
        'message' => 'Need a quote.',
        'status' => 'accepted',
        'accepted_at' => now(),
        'first_response_at' => now()->addMinutes(45),
        'requirements' => ['product_name' => 'Sensor'],
    ], $attributes));
}

beforeEach(function () {
    Mail::fake();
});

describe('quote report digest', function () {
    it('emails every administrator', function () {
        digestAdmin('admin@example.test');
        digestQuote();

        $this->artisan('quotes:report-digest', ['--days' => 30])->assertSuccessful();

        Mail::assertQueued(
            QuoteReportDigestMail::class,
            fn (QuoteReportDigestMail $mail): bool => $mail->hasTo('admin@example.test'),
        );
    });

    it('honours explicit recipients', function () {
        $this->artisan('quotes:report-digest', ['--to' => ['sales@example.test']])
            ->assertSuccessful();

        Mail::assertQueued(
            QuoteReportDigestMail::class,
            fn (QuoteReportDigestMail $mail): bool => $mail->hasTo('sales@example.test'),
        );
    });

    it('accepts comma-separated recipients', function () {
        $this->artisan('quotes:report-digest', ['--to' => ['a@example.test,b@example.test']])
            ->assertSuccessful();

        Mail::assertQueued(QuoteReportDigestMail::class, 2);
    });

    it('writes nothing on a dry run', function () {
        digestAdmin();

        $this->artisan('quotes:report-digest', ['--dry-run' => true])->assertSuccessful();

        Mail::assertNothingQueued();
    });

    it('renders the performance report in the body', function () {
        digestQuote();

        $report = app(BuildQuoteReportAction::class)->execute(30);
        $html = (new QuoteReportDigestMail($report, 30))->render();

        expect($html)
            ->toContain('Quote performance')
            ->toContain('Win rate')
            ->toContain('Top accounts by volume')
            ->toContain('Team performance');
    });
});
