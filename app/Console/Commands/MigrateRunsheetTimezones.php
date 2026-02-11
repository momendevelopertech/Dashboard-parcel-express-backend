<?php

namespace App\Console\Commands;

use App\Models\DriverRunsheet;
use App\Models\DriverRunsheetShipment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateRunsheetTimezones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'runsheets:migrate-timezones 
                            {--timezone=Asia/Muscat : Default timezone for existing records}
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing runsheets to include timezone information';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timezone = $this->option('timezone');
        $dryRun = $this->option('dry-run');

        $this->info("Migrating runsheet timezones to: {$timezone}");

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
            // Migrate driver_runsheets
            $runsheetCount = DriverRunsheet::whereNull('timezone')
                ->orWhere('timezone', '')
                ->count();

            $this->info("Found {$runsheetCount} runsheets without timezone");

            if (!$dryRun && $runsheetCount > 0) {
                DriverRunsheet::whereNull('timezone')
                    ->orWhere('timezone', '')
                    ->update(['timezone' => $timezone]);
                $this->info("✓ Updated {$runsheetCount} runsheets");
            }

            // Migrate driver_runsheet_shipments
            $shipmentCount = DriverRunsheetShipment::whereNull('timezone')
                ->orWhere('timezone', '')
                ->count();

            $this->info("Found {$shipmentCount} runsheet shipments without timezone");

            if (!$dryRun && $shipmentCount > 0) {
                DriverRunsheetShipment::whereNull('timezone')
                    ->orWhere('timezone', '')
                    ->update(['timezone' => $timezone]);
                $this->info("✓ Updated {$shipmentCount} runsheet shipments");
            }

            // Summary
            $this->newLine();
            $this->info("Migration Summary:");
            $this->table(
                ['Table', 'Records Updated'],
                [
                    ['driver_runsheets', $runsheetCount],
                    ['driver_runsheet_shipments', $shipmentCount],
                    ['Total', $runsheetCount + $shipmentCount],
                ]
            );

            if ($dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->warn("Dry run complete - no changes made");
                $this->info("Run without --dry-run to apply changes");
            } else {
                DB::commit();
                $this->newLine();
                $this->info("✓ Migration complete!");
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Migration failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
