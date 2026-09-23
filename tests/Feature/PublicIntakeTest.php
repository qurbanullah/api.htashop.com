<?php

use App\Jobs\Contacts\SendContactMessageNotificationJob;
use App\Jobs\Tickets\SendTicketCreatedEmail;
use App\Models\ContactMessage;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('filesystems.disks.idrivee2.key', 'testing-key');
    config()->set('filesystems.disks.idrivee2.secret', 'testing-secret');
    config()->set('filesystems.disks.idrivee2.region', 'us-east-1');
    config()->set('filesystems.disks.idrivee2.bucket', 'testing-bucket');
    config()->set('filesystems.disks.idrivee2.endpoint', 'https://example.com');
    config()->set('filesystems.disks.idrivee2.use_path_style_endpoint', true);

    Queue::fake();
});

it('stores a public contact message', function () {
    $response = $this->postJson('/api/v1/contact', [
        'first_name' => 'Smoke',
        'last_name' => 'Contact',
        'email' => 'smoke-contact@example.com',
        'company' => 'Volvicon QA',
        'phone' => '+1 555 0100',
        'country' => 'USA',
        'subject' => 'general',
        'message' => 'This is a smoke test contact message.',
        'consent' => true,
        'source_page' => 'https://frontend.volvicon.com/about/contact',
    ]);

    $response->assertCreated()->assertJson([
        'success' => true,
        'message' => 'Your message has been sent successfully. We will get back to you soon.',
    ]);

    $message = ContactMessage::query()->latest('id')->first();

    expect($message)->not->toBeNull();
    expect($message->name)->toBe('Smoke Contact');
    expect($message->email)->toBe('smoke-contact@example.com');
    expect($message->subject)->toBe('General Inquiry');
    expect($message->status)->toBe('new');
    expect($message->metadata['subject_key'])->toBe('general');
    expect($message->metadata['source_page'])->toBe('https://frontend.volvicon.com/about/contact');

    Queue::assertPushed(SendContactMessageNotificationJob::class);
});

it('stores a public support ticket as an internal guest ticket', function () {
    $response = $this->postJson('/api/v1/support/tickets', [
        'name' => 'Smoke Support',
        'email' => 'smoke-support@example.com',
        'category' => 'technical',
        'priority' => 'medium',
        'subject' => 'Smoke support issue',
        'message' => 'This is a smoke test support request from the public website.',
        'source_page' => 'https://frontend.volvicon.com/services/support',
    ]);

    $response->assertCreated()->assertJson([
        'success' => true,
        'message' => 'Support ticket submitted successfully. Our team will review it shortly.',
    ]);

    $ticket = Ticket::query()->latest('id')->first();

    expect($ticket)->not->toBeNull();
    expect($ticket->user_id)->toBeNull();
    expect($ticket->guest_name)->toBe('Smoke Support');
    expect($ticket->guest_email)->toBe('smoke-support@example.com');
    expect($ticket->title)->toBe('Smoke support issue');
    expect($ticket->description)->toContain('smoke test support request');
    expect($ticket->is_visible)->toBeFalse();
    expect($ticket->additional_information)->toContain('Portal access: unavailable for guest submissions.');
    expect($ticket->additional_information)->toContain('Source page: https://frontend.volvicon.com/services/support');

    Queue::assertPushed(SendTicketCreatedEmail::class);
});
