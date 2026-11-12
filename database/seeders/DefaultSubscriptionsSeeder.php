<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultSubscriptionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Subscribe all existing users to post notifications by default
        $users = User::all();

        foreach ($users as $user) {
            // Check if user already has a post subscription
            if (! $user->isSubscribedTo('post')) {
                $user->subscribeTo('post');
            }
        }

        $this->command->info('Default post subscriptions created for all users.');
    }
}
