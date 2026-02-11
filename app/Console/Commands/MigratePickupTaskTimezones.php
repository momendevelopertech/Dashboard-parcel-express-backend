<?php

namespace App\Console\Commands;

use App\Models\MerchantPickupTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePickupTaskTimezones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pickup-tasks:migrate-timezones 
                            {--timezone=Asia/Muscat : Default timezone for existing records}
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill timezone information for existing merchant pickup tasks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timezone = $this->option('timezone');
        $dryRun = $this->option('dry-run');

        $this->info("Migrating merchant pickup task timezones to: {$timezone}");

        if ($dryRun) {
            $this->warn("DRY RUN MODE - No changes will be made");
        }

        // Validate timezone
        if (!in_array($timezone, \DateTimeZone::listIdentifiers())) {
            $this->error("Invalid timezone: {$timezone}");
            return 1;
        }

        DB::beginTransaction();

        try {
            // Count tasks without timezone
            $count = MerchantPickupTask::whereNull('timezone')->count();
            $this->info("Found {$count} pickup tasks without timezone");

            if ($count === 0) {
                $this->info("No pickup tasks to migrate");
                DB::rollBack();
                return 0;
            }

            if (!$dryRun) {
                // Update tasks in batches
                $updated = MerchantPickupTask::whereNull('timezone')
                    ->update([
                        'timezone' => $timezone,
                        'scheduled_timezone' => $timezone,
                    ]);

                $this->info("✓ Updated {$updated} pickup tasks");

                DB::commit();
                $this->info("Migration complete!");
            } else {
                DB::rollBack();
                $this->info("Dry run complete - no changes made");
                $this->info("Would have updated {$count} pickup tasks");
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Migration failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
