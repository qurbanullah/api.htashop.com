<?php

use App\Jobs\User\SendContactMessageNotificationJob;
use App\Mail\Messages\ContactMessageNotificationMail;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

it('sends contact notifications to admin users and the configured contact recipient', function () {
    Role::findOrCreate('admin', 'api');
    Role::findOrCreate('super-admin', 'api');

    config()->set('mail.contact_recipient_email', 'contact@volvicon.com');

    $admin = User::factory()->create([
        'email' => 'admin@volvicon.com',
    ]);
    $admin->assignRole('admin');

    $message = ContactMessage::query()->create([
        'name' => 'Website Visitor',
        'email' => 'visitor@example.com',
        'subject' => 'General Inquiry',
        'message' => 'I would like to know more about Volvicon.',
        'status' => 'new',
        'metadata' => ['source_page' => 'https://frontend.volvicon.com/about/contact'],
    ]);

    Mail::fake();

    $job = new SendContactMessageNotificationJob($message);
    $job->handle();

    Mail::assertSent(ContactMessageNotificationMail::class, 2);
    Mail::assertSent(ContactMessageNotificationMail::class, function (ContactMessageNotificationMail $mail) use ($message) {
        return $mail->contactMessage->is($message)
            && $mail->hasTo('admin@volvicon.com');
    });
    Mail::assertSent(ContactMessageNotificationMail::class, function (ContactMessageNotificationMail $mail) use ($message) {
        return $mail->contactMessage->is($message)
            && $mail->hasTo('contact@volvicon.com');
    });
});

it('renders the contact notification email view', function () {
    $message = ContactMessage::query()->create([
        'name' => 'Website Visitor',
        'email' => 'visitor@example.com',
        'subject' => 'General Inquiry',
        'message' => 'I would like to know more about Volvicon.',
        'status' => 'new',
        'metadata' => [
            'source_page' => 'https://frontend.volvicon.com/about/contact',
            'subject_key' => 'general',
        ],
    ]);

    $html = (new ContactMessageNotificationMail($message))->render();

    expect(Str::contains($html, 'New Contact Message'))->toBeTrue();
    expect(Str::contains($html, 'visitor@example.com'))->toBeTrue();
    expect(Str::contains($html, 'https://frontend.volvicon.com/about/contact'))->toBeTrue();
});
