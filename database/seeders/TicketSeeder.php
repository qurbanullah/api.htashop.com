<?php

namespace Database\Seeders;

use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use App\Enums\StatusEnum;
use App\Enums\StypeEnum;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if tickets already exist
        $existingTicketsCount = Ticket::count();

        if ($existingTicketsCount >= 100) {
            $this->command->info("Found {$existingTicketsCount} existing tickets, skipping ticket creation.");
        } else {
            $this->createTickets();
        }

        // Always try to create messages if they don't exist
        $this->createMessagesForAllTickets();

        // Show some statistics
        $this->showStatistics();
    }

    /**
     * Create tickets
     */
    private function createTickets(): void
    {

        $this->command->info('Starting to seed 100 tickets...');

        // Get all user IDs to distribute tickets among them
        $userIds = User::pluck('id')->toArray();

        if (empty($userIds)) {
            $this->command->error('No users found! Please run UserSeeder first.');
            return;
        }

        // Define distribution strategy for ticket statuses
        $statusDistribution = [
            StatusEnum::OPEN->value => 40,      // 40% open tickets (2000 tickets)
            StatusEnum::CLOSED->value => 25,    // 25% closed tickets (1250 tickets)
            StatusEnum::RESOLVED->value => 25,  // 25% resolved tickets (1250 tickets)
            StatusEnum::ARCHIVED->value => 10,  // 10% archived tickets (500 tickets)
        ];

        // Define distribution for ticket types
        $typeDistribution = [
            StypeEnum::BUG->value => 30,           // 30% bugs (1500 tickets)
            StypeEnum::FEATURE->value => 20,       // 20% feature requests (1000 tickets)
            StypeEnum::QUESTION->value => 25,      // 25% questions (1250 tickets)
            StypeEnum::TECHNICAL->value => 15,     // 15% technical issues (750 tickets)
            StypeEnum::LICENSE->value => 10,       // 10% license issues (500 tickets)
        ];

        $totalTickets = 100;
        $createdTickets = 0;
        $batchSize = 100; // Create tickets in batches for better performance

        // Create tickets based on status distribution
        foreach ($statusDistribution as $status => $percentage) {
            $ticketsForThisStatus = (int) ($totalTickets * $percentage / 100);

            $this->command->info("Creating {$ticketsForThisStatus} tickets with status: {$status}");

            // Create tickets in batches
            for ($i = 0; $i < $ticketsForThisStatus; $i += $batchSize) {
                $currentBatchSize = min($batchSize, $ticketsForThisStatus - $i);

                // Prepare batch data
                $ticketData = [];
                for ($j = 0; $j < $currentBatchSize; $j++) {
                    // Randomly assign user
                    $userId = $userIds[array_rand($userIds)];

                    // Randomly assign ticket type
                    $type = $this->getRandomTypeByDistribution($typeDistribution);

                    // Create ticket using factory but with specific status
                    $factory = Ticket::factory()->make([
                        'user_id' => $userId,
                        'stype' => $type,
                    ]);

                    // Convert to array manually to avoid Carbon casting issues
                    $factoryArray = [
                        'uuid' => $factory->uuid,
                        'user_id' => $factory->user_id,
                        'title' => $factory->title,
                        'slug' => $factory->slug,
                        'stype' => $factory->stype->value,
                        'severity' => $factory->severity->value,
                        'reproducibility' => $factory->reproducibility->value,
                        'priority' => $factory->priority->value,
                        'status' => $factory->status->value,
                        'is_visible' => $factory->is_visible,
                        'is_resolved' => $factory->is_resolved,
                        'is_locked' => $factory->is_locked,
                        'description' => $factory->description,
                        'steps_to_reproduce' => $factory->steps_to_reproduce,
                        'additional_information' => $factory->additional_information,
                        'resolved_on' => $factory->resolved_on ? $factory->resolved_on->format('Y-m-d H:i:s') : null,
                        'archived_on' => $factory->archived_on ? $factory->archived_on->format('Y-m-d H:i:s') : null,
                        'created_at' => $factory->created_at->format('Y-m-d H:i:s'),
                        'updated_at' => $factory->updated_at->format('Y-m-d H:i:s'),
                    ];

                    // Apply status-specific modifications
                    switch ($status) {
                        case StatusEnum::OPEN->value:
                            $factoryArray['status'] = StatusEnum::OPEN->value;
                            $factoryArray['is_resolved'] = false;
                            $factoryArray['resolved_on'] = null;
                            $factoryArray['archived_on'] = null;
                            break;

                        case StatusEnum::CLOSED->value:
                            $factoryArray['status'] = StatusEnum::CLOSED->value;
                            $factoryArray['is_resolved'] = true;
                            $factoryArray['resolved_on'] = now()->subDays(fake()->numberBetween(1, 30))->format('Y-m-d H:i:s');
                            $factoryArray['archived_on'] = null;
                            break;

                        case StatusEnum::RESOLVED->value:
                            $factoryArray['status'] = StatusEnum::RESOLVED->value;
                            $factoryArray['is_resolved'] = true;
                            $factoryArray['resolved_on'] = now()->subDays(fake()->numberBetween(1, 30))->format('Y-m-d H:i:s');
                            $factoryArray['archived_on'] = null;
                            break;

                        case StatusEnum::ARCHIVED->value:
                            $factoryArray['status'] = StatusEnum::ARCHIVED->value;
                            $factoryArray['is_resolved'] = true;
                            $factoryArray['resolved_on'] = now()->subDays(fake()->numberBetween(31, 60))->format('Y-m-d H:i:s');
                            $factoryArray['archived_on'] = now()->subDays(fake()->numberBetween(1, 30))->format('Y-m-d H:i:s');
                            break;
                    }

                    $ticketData[] = $factoryArray;
                }

                // Insert batch
                Ticket::insert($ticketData);
                $createdTickets += $currentBatchSize;

                // Show progress
                $this->command->info("Progress: {$createdTickets}/{$totalTickets} tickets created");
            }
        }

        $this->command->info("Successfully created {$createdTickets} tickets distributed across " . count($userIds) . " users!");
    }

    /**
     * Create messages for all tickets (both new and existing)
     */
    private function createMessagesForAllTickets(): void
    {
        // Get all user IDs for message assignment
        $userIds = User::pluck('id')->toArray();

        if (empty($userIds)) {
            $this->command->error('No users found! Cannot create messages.');
            return;
        }

        $this->createMessagesForTickets($userIds);
    }

    /**
     * Create messages for all tickets
     */
    private function createMessagesForTickets(array $userIds): void
    {
        // Check if messages already exist
        $existingMessagesCount = Message::count();
        if ($existingMessagesCount > 0) {
            $this->command->info("Found {$existingMessagesCount} existing messages, skipping message creation.");
            return;
        }

        $this->command->info('Creating messages for tickets...');

        // Get all tickets in batches to avoid memory issues
        $tickets = Ticket::select('id', 'user_id', 'status', 'created_at')->get();
        $totalTickets = $tickets->count();
        $processedTickets = 0;
        $totalMessages = 0;

        foreach ($tickets as $ticket) {
            $messagesCount = fake()->numberBetween(5, 12); // 5-12 messages per ticket
            $messageData = [];

            // Always create an initial message from the ticket reporter
            $initialMessage = [
                'user_id' => $ticket->user_id,
                'message' => $this->generateInitialMessage(),
                'messageable_type' => 'App\Models\Ticket',
                'messageable_id' => $ticket->id,
                'created_at' => $ticket->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $ticket->created_at->format('Y-m-d H:i:s'),
            ];
            $messageData[] = $initialMessage;

            // Create follow-up messages
            $lastMessageTime = $ticket->created_at;

            for ($i = 1; $i < $messagesCount; $i++) {
                // Determine if this is a support response or user follow-up
                $isSupportResponse = fake()->boolean(40); // 40% chance of support response

                if ($isSupportResponse) {
                    // Get a random admin/support user
                    $supportUsers = User::whereHas('roles', function($query) {
                        $query->whereIn('name', ['admin', 'super-admin']);
                    })->pluck('id')->toArray();

                    $messageUserId = !empty($supportUsers) ? fake()->randomElement($supportUsers) : $ticket->user_id;
                } else {
                    // Message from the ticket reporter or other users
                    $messageUserId = fake()->boolean(80) ? $ticket->user_id : fake()->randomElement($userIds);
                }

                // Create message with realistic timing
                $hoursToAdd = fake()->numberBetween(1, 72); // 1-72 hours between messages
                $lastMessageTime = $lastMessageTime->addHours($hoursToAdd);

                $message = [
                    'user_id' => $messageUserId,
                    'message' => $isSupportResponse ? $this->generateSupportResponse() : $this->generateFollowUpMessage(),
                    'messageable_type' => 'App\Models\Ticket',
                    'messageable_id' => $ticket->id,
                    'created_at' => $lastMessageTime->format('Y-m-d H:i:s'),
                    'updated_at' => $lastMessageTime->format('Y-m-d H:i:s'),
                ];
                $messageData[] = $message;
            }

            // Insert messages for this ticket
            Message::insert($messageData);
            $totalMessages += count($messageData);
            $processedTickets++;

            // Show progress every 100 tickets
            if ($processedTickets % 100 === 0) {
                $this->command->info("Progress: {$processedTickets}/{$totalTickets} tickets processed, {$totalMessages} messages created");
            }
        }

        $this->command->info("Successfully created {$totalMessages} messages for {$processedTickets} tickets!");
    }

    /**
     * Generate initial ticket message
     */
    private function generateInitialMessage(): string
    {
        $initialMessages = [
            "I need help with license activation. The system shows an error when I try to activate my license key.",
            "I'm unable to access the admin panel after the recent update. Getting a 403 forbidden error.",
            "The file upload feature is not working properly. Files get stuck at 50% upload progress.",
            "I'm experiencing slow performance when loading the dashboard. It takes over 30 seconds to load.",
            "There's an issue with email notifications. I'm not receiving any notification emails.",
            "The search functionality returns incorrect results. It's showing outdated data.",
            "I can't generate reports. The system shows 'Export failed' error message.",
            "The user management section is not saving changes. Settings revert after refresh.",
            "I'm getting frequent timeout errors when processing large datasets.",
            "The mobile app crashes when trying to sync data with the server.",
            "I can't update my profile information. The save button doesn't respond.",
            "The API integration is failing with authentication errors.",
            "Database backup functionality is not working. No backup files are being created.",
            "The payment processing module shows 'Transaction failed' for all attempts.",
            "I'm unable to download generated invoices. Links return 404 errors.",
            "The two-factor authentication setup is not working properly.",
            "Calendar integration is not syncing events correctly.",
            "The bulk import feature fails with large CSV files.",
            "Password reset emails are not being delivered to users.",
            "The reporting dashboard shows incorrect data visualization.",
        ];

        return fake()->randomElement($initialMessages);
    }

    /**
     * Generate support response message
     */
    private function generateSupportResponse(): string
    {
        $supportResponses = [
            "Thank you for contacting us. We're looking into this issue and will get back to you shortly.",
            "We've received your report and have escalated it to our technical team for investigation.",
            "Could you please provide your license key and system information so we can better assist you?",
            "We've identified the issue in our logs. A fix will be deployed in the next update.",
            "Please try clearing your browser cache and attempt the operation again.",
            "We've resolved this issue on our end. Please check if it's working now.",
            "This appears to be related to server maintenance. The issue should be resolved now.",
            "We'll need to schedule a quick call to troubleshoot this further. What times work for you?",
            "I've checked your account and everything appears to be configured correctly. Let's try a different approach.",
            "This is a known issue that we're working on. I'll keep you updated on our progress.",
            "Could you please try accessing the system from a different device or network?",
            "I've reset your account permissions. Please log out and back in to see if this resolves the issue.",
            "We've updated your license. You should now be able to access all the features.",
            "I'm escalating this to our senior technical team for further investigation.",
            "Based on your description, this seems to be a browser compatibility issue. Please try using Chrome or Firefox.",
        ];

        return fake()->randomElement($supportResponses);
    }

    /**
     * Generate follow-up message from user
     */
    private function generateFollowUpMessage(): string
    {
        $followUpMessages = [
            "I've tried the suggested solution but the issue persists.",
            "Additional information: This happens on both Chrome and Firefox browsers.",
            "The problem occurs only during peak hours between 9 AM and 5 PM.",
            "I can reproduce this by following these steps: 1) Login, 2) Navigate to settings, 3) Click save.",
            "This is affecting our daily operations. Any updates on the resolution?",
            "Thank you for the quick response! I'll try that and get back to you.",
            "The issue seems to be resolved now. Thank you for your help!",
            "I'm still experiencing the same problem. Could you please look into it further?",
            "Is there an estimated time for when this will be fixed?",
            "I've followed all the troubleshooting steps but nothing has worked so far.",
            "Could you please provide alternative solutions while this is being fixed?",
            "I've attached screenshots that show the error message I'm receiving.",
            "This started happening right after the system update last week.",
            "Other users in my organization are reporting the same issue.",
            "The error message I'm getting is: 'Connection timeout - please try again later'.",
            "I can provide remote access if you need to investigate this directly.",
            "The issue is intermittent - it works sometimes and fails other times.",
            "Could this be related to our firewall or network configuration?",
            "I've checked the logs and found several error entries related to this issue.",
            "Any update on this? This is becoming quite urgent for our business operations.",
        ];

        return fake()->randomElement($followUpMessages);
    }

    /**
     * Get random ticket type based on distribution
     */
    private function getRandomTypeByDistribution(array $typeDistribution): string
    {
        $random = mt_rand(1, 100);
        $cumulative = 0;

        foreach ($typeDistribution as $type => $percentage) {
            $cumulative += $percentage;
            if ($random <= $cumulative) {
                return $type;
            }
        }

        // Fallback
        return array_key_first($typeDistribution);
    }

    /**
     * Show seeding statistics
     */
    private function showStatistics(): void
    {
        $this->command->info("\n=== Ticket Seeding Statistics ===");

        // Status statistics
        $statusStats = DB::table('tickets')
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        $this->command->info("Status Distribution:");
        foreach ($statusStats as $stat) {
            $this->command->info("  {$stat->status}: {$stat->count}");
        }

        // Type statistics
        $typeStats = DB::table('tickets')
            ->select('stype', DB::raw('count(*) as count'))
            ->groupBy('stype')
            ->get();

        $this->command->info("\nType Distribution:");
        foreach ($typeStats as $stat) {
            $this->command->info("  {$stat->stype}: {$stat->count}");
        }

        // User distribution
        $userStats = DB::table('tickets')
            ->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        $this->command->info("\nTop 5 Users by Ticket Count:");
        foreach ($userStats as $stat) {
            $user = User::find($stat->user_id);
            $this->command->info("  {$user->name} ({$user->email}): {$stat->count} tickets");
        }

        $totalTickets = DB::table('tickets')->count();
        $totalUsers = DB::table('tickets')->distinct('user_id')->count();
        $avgTicketsPerUser = round($totalTickets / $totalUsers, 2);

        $this->command->info("\nSummary:");
        $this->command->info("  Total Tickets: {$totalTickets}");
        $this->command->info("  Users with Tickets: {$totalUsers}");
        $this->command->info("  Average Tickets per User: {$avgTicketsPerUser}");

        // Message statistics
        $totalMessages = DB::table('messages')->count();
        if ($totalMessages > 0) {
            $avgMessagesPerTicket = round($totalMessages / $totalTickets, 2);
            $this->command->info("  Total Messages: {$totalMessages}");
            $this->command->info("  Average Messages per Ticket: {$avgMessagesPerTicket}");
        }
    }
}
