<?php

namespace App\Services\Return;

use App\Domain\Pickup\ShipmentPickupFactory;
use App\Models\Shipment;
use App\Models\Scopes\ExcludeReturnShipmentsScope;
use App\Models\Transaction;
use App\Models\DriverAccount;
use App\Models\MerchantAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ReturnFinancialService
 * 
 * Handles financial transactions for return shipments.
 * Refactored from ReversePickupFinancialService to use unified architecture.
 * 
 * Key Changes:
 * - Uses Shipment model instead of ReverseShipment
 * - Uses existing Transaction table instead of ReversePickupTransaction
 * - Simplified logic with single source of truth
 */
class ReturnFinancialService
{
    private const TX_DRIVER_COMMISSION_CREDIT = 'driver_commission_credit';
    private const TX_MERCHANT_FEE_DEBIT = 'merchant_fee_debit';
    private const TX_DRIVER_COMMISSION_REVERSAL = 'driver_commission_credit_reversal';
    private const TX_MERCHANT_FEE_REVERSAL = 'merchant_fee_debit_reversal';

    /**
     * Return flows must bypass Shipment's global scope that hides return shipments.
     */
    private function findReturnShipmentOrFail(int $shipmentId): Shipment
    {
        return Shipment::withoutGlobalScope(ExcludeReturnShipmentsScope::class)
            ->findOrFail($shipmentId);
    }

    /**
     * Credit driver commission when pickup is completed
     * 
     * Step 3: Driver picks up return shipment from customer
     * 
     * @param int $shipmentId
     * @return Transaction
     * @throws \Exception
     */
    public function creditDriverCommission(int $shipmentId): Transaction
    {
        return DB::transaction(function () use ($shipmentId) {
            $shipment = $this->findReturnShipmentOrFail($shipmentId);

            // Validate it's a return shipment
            if (!$shipment->is_return) {
                throw new \Exception('Shipment is not a return shipment');
            }

            // Check if already credited
            $existingTransaction = Transaction::where('shipment_id', $shipment->id)
                ->where('type', self::TX_DRIVER_COMMISSION_CREDIT)
                ->first();

            if ($existingTransaction) {
                throw new \Exception('Driver commission already credited for this shipment');
            }

            $driverId = $shipment->driver_id;
            if (!$driverId) {
                throw new \Exception('No driver assigned to this shipment');
            }

            // Get driver commission from pricing
            $pricingService = app(ReturnPricingService::class);
            $stateId = $shipment->state_id;
            if (!$stateId) {
                $stateId = $shipment->consignee?->state_id;
            }

            $commissionAmount = $pricingService->calculateDriverCommission($driverId, $stateId);

            if ($commissionAmount <= 0) {
                throw new \Exception('Invalid driver commission amount');
            }

            // Credit driver account
            $driverAccount = DriverAccount::firstOrCreate(
                ['driver_id' => $driverId],
                ['balance' => 0]
            );

            $driverAccount->increment('balance', $commissionAmount);

            // Create transaction record
            $transaction = Transaction::create([
                'shipment_id' => $shipment->id,
                'to_id' => $driverId,
                'to_type' => User::class,
                'type' => self::TX_DRIVER_COMMISSION_CREDIT,
                'amount' => $commissionAmount,
                'description' => "Driver commission for return pickup: {$shipment->tracking_no}",
            ]);

            Log::info("Driver commission credited", [
                'shipment_id' => $shipment->id,
                'tracking_no' => $shipment->tracking_no,
                'driver_id' => $driverId,
                'amount' => $commissionAmount,
            ]);
           

      

            return $transaction;
        });
    }

    /**
     * Debit merchant wallet when shipment is delivered
     * 
     * Step 5: Return shipment delivered to merchant
     * 
     * @param int $shipmentId
     * @return Transaction
     * @throws \Exception
     */
    public function debitMerchantWallet(int $shipmentId): Transaction
    {
        return DB::transaction(function () use ($shipmentId) {
            $shipment = $this->findReturnShipmentOrFail($shipmentId);

            // Validate it's a return shipment
            if (!$shipment->is_return) {
                throw new \Exception('Shipment is not a return shipment');
            }

            // Check if already charged
            $existingTransaction = Transaction::where('shipment_id', $shipment->id)
                ->where('type', self::TX_MERCHANT_FEE_DEBIT)
                ->first();

            if ($existingTransaction) {
                throw new \Exception('Merchant already charged for this shipment');
            }

            $merchantId = $shipment->merchant_id;
            if (!$merchantId) {
                throw new \Exception('No merchant associated with this shipment');
            }

            $feeAmount = (float) $shipment->return_fee;

            if ($feeAmount <= 0) {
                Log::warning("Return fee is zero or negative, skipping merchant debit", [
                    'shipment_id' => $shipment->id,
                    'tracking_no' => $shipment->tracking_no,
                ]);
                throw new \Exception('Invalid return fee amount');
            }

            // Debit merchant account
            $merchantAccount = MerchantAccount::firstOrCreate(
                ['merchant_id' => $merchantId],
                ['balance' => 0]
            );

            $merchantAccount->decrement('balance', $feeAmount);

            // Create transaction record
            $transaction = Transaction::create([
                'shipment_id' => $shipment->id,
                'to_id' => $merchantId,
                'to_type' => User::class,
                'type' => self::TX_MERCHANT_FEE_DEBIT,
                'amount' => $feeAmount,
                'description' => "Return fee for shipment: {$shipment->tracking_no}",
            ]);

            // Calculate and log company revenue
            $driverCommission = $this->getDriverCommission($shipment->id);
            $companyRevenue = $feeAmount - $driverCommission;

            Log::info("Return shipment revenue", [
                'shipment_id' => $shipment->id,
                'tracking_no' => $shipment->tracking_no,
                'merchant_fee' => $feeAmount,
                'driver_commission' => $driverCommission,
                'company_revenue' => $companyRevenue,
            ]);

            return $transaction;
        });
    }

    /**
     * Reverse all transactions for a shipment (for cancellations)
     * 
     * @param int $shipmentId
     * @return void
     */
    public function reverseTransactions(int $shipmentId): void
    {
        DB::transaction(function () use ($shipmentId) {
            $shipment = $this->findReturnShipmentOrFail($shipmentId);

            // Get all transactions for this shipment
            $transactions = Transaction::where('shipment_id', $shipment->id)
                ->whereIn('type', [
                    self::TX_DRIVER_COMMISSION_CREDIT,
                    self::TX_MERCHANT_FEE_DEBIT,
                ])
                ->get();

            foreach ($transactions as $transaction) {
                // Reverse driver commission
                if ($transaction->type === self::TX_DRIVER_COMMISSION_CREDIT) {
                    $alreadyReversed = Transaction::where('shipment_id', $shipment->id)
                        ->where('type', self::TX_DRIVER_COMMISSION_REVERSAL)
                        ->exists();

                    if ($alreadyReversed) {
                        continue;
                    }

                    $driverAccount = DriverAccount::where('driver_id', $transaction->to_id)->first();
                    if ($driverAccount) {
                        $driverAccount->decrement('balance', $transaction->amount);
                    }

                    Transaction::create([
                        'shipment_id' => $shipment->id,
                        'to_id' => $transaction->to_id,
                        'to_type' => $transaction->to_type,
                        'type' => self::TX_DRIVER_COMMISSION_REVERSAL,
                        'amount' => $transaction->amount,
                        'description' => "Reversal of return pickup driver commission: {$shipment->tracking_no}",
                    ]);
                }

                // Reverse merchant fee
                if ($transaction->type === self::TX_MERCHANT_FEE_DEBIT) {
                    $alreadyReversed = Transaction::where('shipment_id', $shipment->id)
                        ->where('type', self::TX_MERCHANT_FEE_REVERSAL)
                        ->exists();

                    if ($alreadyReversed) {
                        continue;
                    }

                    $merchantAccount = MerchantAccount::where('merchant_id', $transaction->to_id)->first();
                    if ($merchantAccount) {
                        $merchantAccount->increment('balance', $transaction->amount);
                    }

                    Transaction::create([
                        'shipment_id' => $shipment->id,
                        'to_id' => $transaction->to_id,
                        'to_type' => $transaction->to_type,
                        'type' => self::TX_MERCHANT_FEE_REVERSAL,
                        'amount' => $transaction->amount,
                        'description' => "Reversal of merchant return fee: {$shipment->tracking_no}",
                    ]);
                }

                Log::info("Transaction reversed", [
                    'transaction_id' => $transaction->id,
                    'shipment_id' => $shipment->id,
                    'tracking_no' => $shipment->tracking_no,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                ]);
            }
        });
    }

    /**
     * Get financial summary for a return shipment
     * 
     * @param int $shipmentId
     * @return array
     */
    public function getFinancialSummary(int $shipmentId): array
    {
        $shipment = Shipment::withoutGlobalScope(ExcludeReturnShipmentsScope::class)
            ->with('transactions')
            ->findOrFail($shipmentId);

        if (!$shipment->is_return) {
            throw new \Exception('Shipment is not a return shipment');
        }

        $driverTransaction = $shipment->transactions()
            ->where('type', self::TX_DRIVER_COMMISSION_CREDIT)
            ->first();

        $merchantTransaction = $shipment->transactions()
            ->where('type', self::TX_MERCHANT_FEE_DEBIT)
            ->first();

        $driverCommission = $driverTransaction ? (float) $driverTransaction->amount : 0;
        $merchantFee = $merchantTransaction ? (float) $merchantTransaction->amount : 0;
        $companyRevenue = $merchantFee - $driverCommission;

        return [
            'shipment_id' => $shipment->id,
            'tracking_no' => $shipment->tracking_no,
            'is_return' => $shipment->is_return,
            'return_fee_before_discount' => (float) $shipment->return_fee_before_discount,
            'return_fee_discount' => (float) $shipment->return_fee_discount,
            'return_fee' => (float) $shipment->return_fee,
            'driver_commission' => $driverCommission,
            'driver_commission_paid_at' => $driverTransaction?->created_at,
            'merchant_fee' => $merchantFee,
            'merchant_fee_charged_at' => $merchantTransaction?->created_at,
            'company_revenue' => $companyRevenue,
        ];
    }

    /**
     * Get driver commission amount for a shipment
     * 
     * @param int $shipmentId
     * @return float
     */
    private function getDriverCommission(int $shipmentId): float
    {
        $transaction = Transaction::where('shipment_id', $shipmentId)
            ->where('type', self::TX_DRIVER_COMMISSION_CREDIT)
            ->first();

        return $transaction ? (float) $transaction->amount : 0;
    }
}
