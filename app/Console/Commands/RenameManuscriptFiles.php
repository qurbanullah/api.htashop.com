<?php

namespace App\Console\Commands;

use App\Models\Manuscript;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RenameManuscriptFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'manuscripts:rename-files
                            {--dry-run : Run without making changes}
                            {--limit= : Limit number of manuscripts to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rename existing manuscript and revision files to use manuscript numbers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        $this->info('Starting manuscript file renaming process...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Get manuscripts with manuscript_number and media files
        $query = Manuscript::whereNotNull('manuscript_number')
            ->has('media');

        if ($limit) {
            $query->limit((int) $limit);
        }

        $manuscripts = $query->get();

        $this->info("Found {$manuscripts->count()} manuscripts to process");

        $bar = $this->output->createProgressBar($manuscripts->count());
        $bar->start();

        $renamedCount = 0;
        $errorCount = 0;

        foreach ($manuscripts as $manuscript) {
            try {
                // Rename manuscript file
                $manuscriptFile = $manuscript->getFirstMedia('manuscript_file');
                if ($manuscriptFile) {
                    $extension = pathinfo($manuscriptFile->file_name, PATHINFO_EXTENSION);
                    $newFileName = "{$manuscript->manuscript_number}.{$extension}";

                    if ($manuscriptFile->file_name !== $newFileName) {
                        if (!$dryRun) {
                            $manuscriptFile->file_name = $newFileName;
                            $manuscriptFile->name = $manuscript->manuscript_number;
                            $manuscriptFile->save();
                        }

                        $this->newLine();
                        $this->info("  ✓ Manuscript {$manuscript->id}: {$manuscriptFile->file_name} → {$newFileName}");
                        $renamedCount++;
                    }
                }

                // Rename revision files
                $revisionFiles = $manuscript->getMedia('revision_files');
                foreach ($revisionFiles as $media) {
                    $revisionId = $media->getCustomProperty('revision_id');
                    $versionNumber = $media->getCustomProperty('version_number');

                    if ($revisionId && $versionNumber) {
                        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION);
                        $newFileName = "{$manuscript->manuscript_number}_R{$versionNumber}.{$extension}";

                        if ($media->file_name !== $newFileName) {
                            if (!$dryRun) {
                                $media->file_name = $newFileName;
                                $media->name = "{$manuscript->manuscript_number} - Revision {$versionNumber}";
                                $media->save();
                            }

                            $this->newLine();
                            $this->info("  ✓ Revision {$revisionId}: {$media->file_name} → {$newFileName}");
                            $renamedCount++;
                        }
                    }
                }

            } catch (\Exception $e) {
                $this->newLine();
                $this->error("  ✗ Error processing manuscript {$manuscript->id}: {$e->getMessage()}");
                Log::error('Failed to rename manuscript files', [
                    'manuscript_id' => $manuscript->id,
                    'error' => $e->getMessage()
                ]);
                $errorCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info('=== Renaming Summary ===');
        $this->info("Total processed: {$manuscripts->count()}");
        $this->info("Files renamed: {$renamedCount}");

        if ($errorCount > 0) {
            $this->error("Errors: {$errorCount}");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('DRY RUN - No changes were made. Run without --dry-run to apply changes.');
        }

        return 0;
    }
}
