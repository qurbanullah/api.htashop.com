<?php

namespace App\Console\Commands;

use App\Services\Auth\RefreshTokenService;
use Illuminate\Console\Command;

/**
 * Deletes refresh tokens that expired longer ago than the configured retention
 * window. Expired rows are kept briefly for audit ("which device, when, why")
 * before removal.
 *
 * Scheduled daily — see routes/console.php.
 */
class PruneRefreshTokensCommand extends Command
{
    protected $signature = 'auth:prune-refresh-tokens';

    protected $description = 'Prune expired refresh tokens past their retention window';

    public function handle(RefreshTokenService $refreshTokenService): int
    {
        $deleted = $refreshTokenService->prune();

        $this->info("Pruned {$deleted} refresh token(s).");

        return self::SUCCESS;
    }
}
