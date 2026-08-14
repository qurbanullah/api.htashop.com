<?php

namespace App\Actions\User\Email;

use App\Models\Email;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SetPrimaryEmailAction
{
    public function handle(User $user, Email $email): void
    {
        DB::transaction(function () use ($user, $email) {
            $user->emails()->where('is_primary', true)->update(['is_primary' => false]);
            $email->update(['is_primary' => true]);
            $user->update(['email' => $email->email]);
        });
    }
}
