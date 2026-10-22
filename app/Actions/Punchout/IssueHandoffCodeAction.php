<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;
use Illuminate\Support\Str;

class IssueHandoffCodeAction
{
    /**
     * Issue a single-use code for the browser hand-off. The long-lived session
     * token never appears in the catalog URL.
     */
    public function handle(PunchoutSession $session): string
    {
        $code = Str::random(64);
        $ttl = max(30, (int) config('punchout.handoff_code_ttl_seconds', 120));

        $session->update([
            'handoff_code_hash' => hash('sha256', $code),
            'handoff_code_expires_at' => now()->addSeconds($ttl),
            'handoff_code_used_at' => null,
        ]);

        return $code;
    }
}
