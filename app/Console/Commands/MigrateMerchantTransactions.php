<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MerchantTransaction;
use App\Models\MerchantCommissionTransaction;
use App\Models\PickuptaskTransaction;
use App\Models\MerchantSettlement;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use App\Services\CalculationLogicService;

class MigrateMerchantTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:merchant-transactions {--force : Force migration even if already done (may cause duplicates if not careful)}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Migrate historical merchant transactions from legacy tables to the unified merchant_transactions table';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting migration of merchant transactions...');
        $calc = app(CalculationLogicService::class);

        // 1. Migrate Commission Transactions
        $this->migrateCommissions();

        // 2. Migrate Pickup Deposits
        $this->migratePickupDeposits();

        // 3. Migrate Settlements
        $this->migrateSettlements();

        // 4. Migrate COD from Shipments
        $this->migrateCOD($calc);

        $this->info('Migration completed successfully.');
        return 0;
    }

    protected function migrateCommissions()
    {
        $this->info('Migrating merchant commission transactions...');
        $commissions = MerchantCommissionTransaction::all();
        $bar = $this->output->createProgressBar(count($commissions));

        foreach ($commissions as $comm) {
            // Delivery Fee
            if ($comm->base_delivery_fee > 0 && strtolower($comm->fee_payer ?? '') === 'merchant') {
                $this->createIfNotExists([
                    'merchant_id' => $comm->merchant_id,
                    'type' => MerchantTransaction::TYPE_DELIVERY_FEE,
                    'shipment_id' => $comm->shipment_id,
                    'transactionable_type' => Shipment::class,
                    'transactionable_id' => $comm->shipment_id,
                    'country_id' => $comm->country_id,
                    'state_id' => $comm->state_id,
                    'amount' => -1 * abs($comm->base_delivery_fee),
                    'base_amount' => $comm->base_delivery_fee,
                    'discount_amount' => $comm->delivery_discount_amount,
                    'fee_payer' => $comm->fee_payer,
                    'description' => "Legacy: Delivery fee (Base)",
                    'status' => MerchantTransaction::STATUS_COMPLETED,
                    'created_at' => $comm->created_at,
                    'completed_at' => $comm->created_at,
                ]);
            }

            // Delivery Discount
            if ($comm->delivery_discount_amount > 0 && strtolower($comm->fee_payer ?? '') === 'merchant') {
                $this->createIfNotExists([
                    'merchant_id' => $comm->merchant_id,
                    'type' => MerchantTransaction::TYPE_DELIVERY_DISCOUNT,
                    'shipment_id' => $comm->shipment_id,
                    'transactionable_type' => Shipment::class,
                    'transactionable_id' => $comm->shipment_id,
                    'country_id' => $comm->country_id,
                    'state_id' => $comm->state_id,
                    'amount' => abs($comm->delivery_discount_amount),
                    'description' => "Legacy: Delivery fee discount",
                    'status' => MerchantTransaction::STATUS_COMPLETED,
                    'created_at' => $comm->created_at,
                    'completed_at' => $comm->created_at,
                ]);
            }

            // Return Fee
            if ($comm->base_return_fee > 0 && strtolower($comm->fee_payer ?? '') === 'merchant') {
                $this->createIfNotExists([
                    'merchant_id' => $comm->merchant_id,
                    'type' => MerchantTransaction::TYPE_RETURN_FEE,
                    'shipment_id' => $comm->shipment_id,
                    'transactionable_type' => Shipment::class,
                    'transactionable_id' => $comm->shipment_id,
                    'country_id' => $comm->country_id,
                    'state_id' => $comm->state_id,
                    'amount' => -1 * abs($comm->base_return_fee),
                    'base_amount' => $comm->base_return_fee,
                    'discount_amount' => $comm->return_discount_amount,
                    'fee_payer' => $comm->fee_payer,
                    'description' => "Legacy: Return fee (Base)",
                    'status' => MerchantTransaction::STATUS_COMPLETED,
                    'created_at' => $comm->created_at,
                    'completed_at' => $comm->created_at,
                ]);
            }

            // Return Discount
            if ($comm->return_discount_amount > 0 && strtolower($comm->fee_payer ?? '') === 'merchant') {
                $this->createIfNotExists([
                    'merchant_id' => $comm->merchant_id,
                    'type' => MerchantTransaction::TYPE_RETURN_DISCOUNT,
                    'shipment_id' => $comm->shipment_id,
                    'transactionable_type' => Shipment::class,
                    'transactionable_id' => $comm->shipment_id,
                    'country_id' => $comm->country_id,
                    'state_id' => $comm->state_id,
                    'amount' => abs($comm->return_discount_amount),
                    'description' => "Legacy: Return fee discount",
                    'status' => MerchantTransaction::STATUS_COMPLETED,
                    'created_at' => $comm->created_at,
                    'completed_at' => $comm->created_at,
                ]);
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    protected function migratePickupDeposits()
    {
        $this->info('Migrating pickup deposits...');
        $deposits = PickuptaskTransaction::with('pickuptask')->get();
        $bar = $this->output->createProgressBar(count($deposits));

        foreach ($deposits as $dep) {
            if (!$dep->pickuptask) continue;

            $this->createIfNotExists([
                'merchant_id' => $dep->pickuptask->merchant_id,
                'type' => MerchantTransaction::TYPE_PICKUP_DEPOSIT,
                'pickup_task_id' => $dep->pickuptask_id,
                'transactionable_type' => \App\Models\MerchantPickupTask::class,
                'transactionable_id' => $dep->pickuptask_id,
                'reference' => $dep->pickuptask->ref,
                'amount' => (float) $dep->amount,
                'description' => "Legacy: Pickup deposit for task {$dep->pickuptask->ref}",
                'status' => MerchantTransaction::STATUS_COMPLETED,
                'created_at' => $dep->created_at,
                'completed_at' => $dep->created_at,
                'paid_by_cash' => $dep->paid_by_cash,
                'paid_by_bank' => $dep->paid_by_bank,
            ]);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    protected function migrateSettlements()
    {
        $this->info('Migrating settlements...');
        $settlements = MerchantSettlement::all();
        $bar = $this->output->createProgressBar(count($settlements));

        foreach ($settlements as $sett) {
            $this->createIfNotExists([
                'merchant_id' => $sett->merchant_id,
                'type' => MerchantTransaction::TYPE_SETTLEMENT,
                'reference' => $sett->reference,
                'amount' => -1 * abs($sett->amount),
                'description' => $sett->notes ?: "Legacy: Settlement payout",
                'status' => MerchantTransaction::STATUS_COMPLETED,
                'created_at' => $sett->created_at,
                'completed_at' => $sett->created_at,
                'receipt_path' => $sett->receipt_path,
                'metadata' => [
                    'legacy_id' => $sett->id,
                    'receipt_uploaded_by' => $sett->receipt_uploaded_by,
                    'receipt_uploaded_at' => $sett->receipt_uploaded_at,
                ],
            ]);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    protected function migrateCOD($calc)
    {
        $this->info('Migrating COD from DELIVERED shipments...');
        // We only migrate COD for shipments that are DELIVERED and have a merchant_id
        $shipments = Shipment::where('status', 'DELIVERED')
            ->whereNotNull('merchant_id')
            ->where('payment_type', 'COD')
            ->get();

        $bar = $this->output->createProgressBar(count($shipments));

        foreach ($shipments as $shipment) {
            $codAmount = $calc->getMerchantCOD($shipment);

            if ($codAmount > 0) {
                $this->createIfNotExists([
                    'merchant_id' => $shipment->merchant_id,
                    'type' => MerchantTransaction::TYPE_COD_COLLECTED,
                    'shipment_id' => $shipment->id,
                    'transactionable_type' => Shipment::class,
                    'transactionable_id' => $shipment->id,
                    'amount' => $codAmount,
                    'description' => "Legacy: COD collected for shipment {$shipment->tracking_no}",
                    'status' => MerchantTransaction::STATUS_COMPLETED,
                    'created_at' => $shipment->updated_at, // Use updated_at as delivery date estimation
                    'completed_at' => $shipment->updated_at,
                ]);
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    protected function createIfNotExists(array $data)
    {
        $query = MerchantTransaction::where('merchant_id', $data['merchant_id'])
            ->where('type', $data['type']);

        if (isset($data['shipment_id'])) {
            $query->where('shipment_id', $data['shipment_id']);
        }
        if (isset($data['pickup_task_id'])) {
            $query->where('pickup_task_id', $data['pickup_task_id']);
        }
        if (isset($data['reference']) && !isset($data['shipment_id']) && !isset($data['pickup_task_id'])) {
            $query->where('reference', $data['reference']);
        }

        if (!$query->exists() || $this->option('force')) {
            MerchantTransaction::create($data);
        }
    }
}
