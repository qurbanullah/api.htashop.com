<?php

namespace App\Actions\User\Email;

use App\Models\Email;
use App\Models\User;

class AddEmailAction
{
    public function handle(User $user, string $emailAddress, bool $isPrimary = false): Email
    {
        $email = $user->emails()->create([
            'email' => $emailAddress,
            'is_primary' => $isPrimary,
            'is_verified' => false,
        ]);

        if ($isPrimary) {
            app(SetPrimaryEmailAction::class)->handle($user, $email);
        }

        return $email;
    }
}
