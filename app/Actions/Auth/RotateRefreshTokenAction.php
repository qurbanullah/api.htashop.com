<?php

namespace App\Actions\Auth;

use App\Enums\RefreshTokenRevokedReasonEnum;
use App\Models\RefreshToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Advances a session to its next rotation step.
 *
 * The presented token is retired and linked to its successor in the same
 * transaction, so a crash can never leave a family with two usable tokens (which
 * would make reuse detection meaningless).
 */
class RotateRefreshTokenAction
{
    /**
     * @param  array<string, mixed>  $attributes  attributes for the successor
     */
    public function execute(
        RefreshToken $current,
        array $attributes,
        RefreshTokenRevokedReasonEnum $reason = RefreshTokenRevokedReasonEnum::ROTATED,
    ): RefreshToken {
        return DB::transaction(function () use ($current, $attributes, $reason): RefreshToken {
            $successor = RefreshToken::query()->create($attributes + [
                'user_id' => $current->user_id,
                // The lineage lives on so a later replay can revoke all of it.
                'family_id' => $current->family_id,
                'device_id' => $current->device_id,
            ]);

            $current->fill([
                'revoked_at' => now(),
                'revoked_reason' => $reason,
                'replaced_by_id' => $successor->id,
                'last_used_at' => now(),
            ])->save();

            Log::info('Refresh token rotated', [
                'user_id' => $current->user_id,
                'family_id' => $current->family_id,
                'previous_id' => $current->id,
                'refresh_token_id' => $successor->id,
                'reason' => $reason->value,
            ]);

            return $successor;
        });
    }
}
