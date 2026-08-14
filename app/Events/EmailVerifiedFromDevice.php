<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmailVerifiedFromDevice
{
    use Dispatchable, SerializesModels;

    public $user;

    /**
     * Create a new event instance.
     *
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the user data for logging or other purposes.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'email_verified' => true,
            'verified_at' => now()->toISOString(),
        ];
    }
}
