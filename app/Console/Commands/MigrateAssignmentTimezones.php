<?php

namespace App\Console\Commands;

use App\Models\DriverShipmentAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateAssignmentTimezones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assignments:migrate-timezones 
                            {--timezone=Asia/Muscat : Default timezone for existing records}
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill timezone information for existing driver shipment assignments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timezone = $this->option('timezone');
        $dryRun = $this->option('dry-run');

        $this->info("Migrating driver shipment assignment timezones to: {$timezone}");

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
            // Count assignments without timezone
            $count = DriverShipmentAssignment::whereNull('timezone')->count();
            $this->info("Found {$count} assignments without timezone");

            if ($count === 0) {
                $this->info("No assignments to migrate");
                DB::rollBack();
                return 0;
            }

            if (!$dryRun) {
                // Update assignments in batches
                $updated = DriverShipmentAssignment::whereNull('timezone')
                    ->update(['timezone' => $timezone]);

                $this->info("✓ Updated {$updated} assignments");

                DB::commit();
                $this->info("Migration complete!");
            } else {
                DB::rollBack();
                $this->info("Dry run complete - no changes made");
                $this->info("Would have updated {$count} assignments");
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Migration failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
