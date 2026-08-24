<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class NewsletterSubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Subscribe all existing users to newsletter by default
        $users = User::all();

        foreach ($users as $user) {
            // Check if user already has newsletter subscription
            if (!$user->isSubscribedTo('newsletter')) {
                $user->subscribeTo('newsletter');
            }
        }

        $this->command->info('Default newsletter subscriptions created for all users.');
    }
}
