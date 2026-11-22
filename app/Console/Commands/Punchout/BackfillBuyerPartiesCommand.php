<?php

namespace App\Console\Commands\Punchout;

use App\Actions\Punchout\EnsureBuyerPartyAction;
use App\Models\PunchoutBuyer;
use Illuminate\Console\Command;

/**
 * Gives every existing buyer relationship a party.
 *
 * Safe to run repeatedly: only buyers with a null `buyer_party_id` are touched,
 * and a network id (Ariba ANID / DUNS) causes the buyer to join the party
 * already used by other tenants selling to the same buyer.
 */
class BackfillBuyerPartiesCommand extends Command
{
    protected $signature = 'punchout:backfill-buyer-parties {--dry-run : Report how many buyers need a party without writing}';

    protected $description = 'Assign a buyer party to every punchout buyer relationship';

    public function handle(EnsureBuyerPartyAction $ensureBuyerPartyAction): int
    {
        $pending = PunchoutBuyer::query()->whereNull('buyer_party_id')->count();

        if ($this->option('dry-run')) {
            $this->info("Would assign a party to {$pending} buyer(s).");

            return self::SUCCESS;
        }

        if ($pending === 0) {
            $this->info('Every buyer already has a party.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($pending);
        $bar->start();
        $processed = 0;

        PunchoutBuyer::query()
            ->whereNull('buyer_party_id')
            ->chunkById(200, function ($buyers) use ($ensureBuyerPartyAction, $bar, &$processed): void {
                foreach ($buyers as $buyer) {
                    $ensureBuyerPartyAction->handle($buyer);
                    $processed++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("Assigned a party to {$processed} buyer(s).");

        return self::SUCCESS;
    }
}
