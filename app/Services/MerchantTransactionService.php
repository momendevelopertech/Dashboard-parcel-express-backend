<?php

namespace App\Services;

use App\Models\MerchantTransaction;
use App\Models\Shipment;
use App\Models\MerchantPickupTask;
use App\Models\PickuptaskTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MerchantTransactionService
{
    /**
     * Record delivery and return fees for a shipment
     * 
     * @param Shipment $shipment
     * @return array Array of created transactions
     */
    public function recordShipmentFees(Shipment $shipment): array
    {
        if (!$shipment->merchant_id) {
            return [];
        }

        $transactions = [];

        DB::transaction(function () use ($shipment, &$transactions) {
            $calc = app(\App\Services\CalculationLogicService::class);

            $baseDeliveryFee = $calc->getBaseDeliveryFee($shipment);
            $deliveryDiscount = (float) ($shipment->delivery_fee_discount ?? 0);

            $baseReturnFee = (float) ($shipment->return_fee_before_discount ?? $shipment->return_fee ?? 0);
            $returnDiscount = (float) ($shipment->return_fee_discount ?? 0);

            // Record delivery fee (Base amount - always deduction if merchant pays)
            if ($baseDeliveryFee > 0 && strtolower($shipment->fee_payer ?? '') == 'merchant') {
                // Idempotency check for delivery fee
                if (!MerchantTransaction::where('shipment_id', $shipment->id)->where('type', MerchantTransaction::TYPE_DELIVERY_FEE)->exists()) {
                    $transactions[] = $this->createTransaction([
                        'merchant_id' => $shipment->merchant_id,
                        'type' => MerchantTransaction::TYPE_DELIVERY_FEE,
                        'shipment_id' => $shipment->id,
                        'transactionable_type' => Shipment::class,
                        'transactionable_id' => $shipment->id,
                        'country_id' => $shipment->country_id,
                        'state_id' => $shipment->state_id,
                        'amount' => -1 * abs($baseDeliveryFee), // Deduction
                        'base_amount' => $baseDeliveryFee,
                        'discount_amount' => $deliveryDiscount,
                        'fee_payer' => $shipment->fee_payer,
                        'description' => "Delivery fee for shipment {$shipment->tracking_no} (Base)",
                        'status' => MerchantTransaction::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
                }
            }

            // Record delivery discount (Offset credit)
            if ($deliveryDiscount > 0 && strtolower($shipment->fee_payer ?? '') === 'merchant') {
                // Idempotency check for delivery discount
                if (!MerchantTransaction::where('shipment_id', $shipment->id)->where('type', MerchantTransaction::TYPE_DELIVERY_DISCOUNT)->exists()) {
                    $transactions[] = $this->createTransaction([
                        'merchant_id' => $shipment->merchant_id,
                        'type' => MerchantTransaction::TYPE_DELIVERY_DISCOUNT,
                        'shipment_id' => $shipment->id,
                        'transactionable_type' => Shipment::class,
                        'transactionable_id' => $shipment->id,
                        'country_id' => $shipment->country_id,
                        'state_id' => $shipment->state_id,
                        'amount' => abs($deliveryDiscount), // Credit
                        'description' => "Delivery fee discount for shipment {$shipment->tracking_no}",
                        'status' => MerchantTransaction::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
                }
            }

            // Record return fee (Base amount - Deduction)
            if ($baseReturnFee > 0 && strtolower($shipment->fee_payer ?? '') === 'merchant') {
                if (!MerchantTransaction::where('shipment_id', $shipment->id)->where('type', MerchantTransaction::TYPE_RETURN_FEE)->exists()) {
                    $transactions[] = $this->createTransaction([
                        'merchant_id' => $shipment->merchant_id,
                        'type' => MerchantTransaction::TYPE_RETURN_FEE,
                        'shipment_id' => $shipment->id,
                        'transactionable_type' => Shipment::class,
                        'transactionable_id' => $shipment->id,
                        'country_id' => $shipment->country_id,
                        'state_id' => $shipment->state_id,
                        'amount' => -1 * abs($baseReturnFee),
                        'base_amount' => $baseReturnFee,
                        'discount_amount' => $returnDiscount,
                        'fee_payer' => $shipment->fee_payer,
                        'description' => "Return fee for shipment {$shipment->tracking_no} (Base)",
                        'status' => MerchantTransaction::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
                }
            }

            // Record return discount (Offset credit)
            if ($returnDiscount > 0 && strtolower($shipment->fee_payer ?? '') === 'merchant') {
                if (!MerchantTransaction::where('shipment_id', $shipment->id)->where('type', MerchantTransaction::TYPE_RETURN_DISCOUNT)->exists()) {
                    $transactions[] = $this->createTransaction([
                        'merchant_id' => $shipment->merchant_id,
                        'type' => MerchantTransaction::TYPE_RETURN_DISCOUNT,
                        'shipment_id' => $shipment->id,
                        'transactionable_type' => Shipment::class,
                        'transactionable_id' => $shipment->id,
                        'country_id' => $shipment->country_id,
                        'state_id' => $shipment->state_id,
                        'amount' => abs($returnDiscount),
                        'description' => "Return fee discount for shipment {$shipment->tracking_no}",
                        'status' => MerchantTransaction::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
                }
            }


            //when fee payer is customer and commison on delivery fees must rebate to merchant
            if ($deliveryDiscount > 0 && strtolower($shipment->fee_payer ?? '') === 'customer') {
                if (!MerchantTransaction::where('shipment_id', $shipment->id)->where('type', MerchantTransaction::TYPE_DELIVERY_REBATE_TO_MERCHANT)->exists()) {
                    $transactions[] = $this->createTransaction([
                        'merchant_id' => $shipment->merchant_id,
                        'type' => MerchantTransaction::TYPE_DELIVERY_REBATE_TO_MERCHANT,
                        'shipment_id' => $shipment->id,
                        'transactionable_type' => Shipment::class,
                        'transactionable_id' => $shipment->id,
                        'country_id' => $shipment->country_id,
                        'state_id' => $shipment->state_id,
                        'amount' => abs($deliveryDiscount), // Credit
                        'description' => "Delivery fee Rebated to Merchant from Consignee for shipment {$shipment->tracking_no}",
                        'status' => MerchantTransaction::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
                }
            }
        });

        return array_filter($transactions);
    }

    public function recordReverseShipmentFees(Shipment $shipment): array
    {
        if (!$shipment->merchant_id) {
            return [];
        }

        $transactions = [];

        DB::transaction(function () use ($shipment, &$transactions) {
            $calc = app(\App\Services\CalculationLogicService::class);

            $returnFee = $shipment->return_fee_before_discount ?? $shipment->return_fee ?? 0;

            // Record delivery fee (Base amount - always deduction if merchant pays)
            if ($returnFee > 0 ) {
                // Idempotency check for delivery fee
                if (!MerchantTransaction::where('shipment_id', $shipment->id)->where('type', MerchantTransaction::TYPE_REVERSE_DELIVERY_FEE)->exists()) {
                    $transactions[] = $this->createTransaction([
                        'merchant_id' => $shipment->merchant_id,
                        'type' => MerchantTransaction::TYPE_REVERSE_DELIVERY_FEE,
                        'shipment_id' => $shipment->id,
                        'transactionable_type' => Shipment::class,
                        'transactionable_id' => $shipment->id,
                        'country_id' => $shipment->country_id,
                        'state_id' => $shipment->state_id,
                        'amount' => -1 * abs($baseReturnFee), // Deduction
                        'base_amount' => $baseReturnFee,
                        'discount_amount' => $returnDiscount,
                        'fee_payer' => $shipment->fee_payer,
                        'description' => "Delivery fee for shipment {$shipment->tracking_no} (Base)",
                        'status' => MerchantTransaction::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
                }
            }

            // Record delivery discount (Offset credit)
            if ($returnDiscount > 0 && strtolower($shipment->fee_payer ?? '') === 'merchant') {
                // Idempotency check for delivery discount
                if (!MerchantTransaction::where('shipment_id', $shipment->id)->where('type', MerchantTransaction::TYPE_DELIVERY_DISCOUNT)->exists()) {
                    $transactions[] = $this->createTransaction([
                        'merchant_id' => $shipment->merchant_id,
                        'type' => MerchantTransaction::TYPE_DELIVERY_DISCOUNT,
                        'shipment_id' => $shipment->id,
                        'transactionable_type' => Shipment::class,
                        'transactionable_id' => $shipment->id,
                        'country_id' => $shipment->country_id,
                        'state_id' => $shipment->state_id,
                        'amount' => abs($returnDiscount), // Credit
                        'description' => "Delivery fee discount for shipment {$shipment->tracking_no}",
                        'status' => MerchantTransaction::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
                }
            }

            // Record return fee (Base amount - Deduction)
            if ($baseReturnFee > 0 && strtolower($shipment->fee_payer ?? '') === 'merchant') {
                if (!MerchantTransaction::where('shipment_id', $shipment->id)->where('type', MerchantTransaction::TYPE_RETURN_FEE)->exists()) {
                    $transactions[] = $this->createTransaction([
                        'merchant_id' => $shipment->merchant_id,
                        'type' => MerchantTransaction::TYPE_RETURN_FEE,
                        'shipment_id' => $shipment->id,
                        'transactionable_type' => Shipment::class,
                        'transactionable_id' => $shipment->id,
                        'country_id' => $shipment->country_id,
                        'state_id' => $shipment->state_id,
                        'amount' => -1 * abs($baseReturnFee),
                        'base_amount' => $baseReturnFee,
                        'discount_amount' => $returnDiscount,
                        'fee_payer' => $shipment->fee_payer,
                        'description' => "Return fee for shipment {$shipment->tracking_no} (Base)",
                        'status' => MerchantTransaction::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
                }
            }

            // Record return discount (Offset credit)
            if ($returnDiscount > 0 && strtolower($shipment->fee_payer ?? '') === 'merchant') {
                if (!MerchantTransaction::where('shipment_id', $shipment->id)->where('type', MerchantTransaction::TYPE_RETURN_DISCOUNT)->exists()) {
                    $transactions[] = $this->createTransaction([
                        'merchant_id' => $shipment->merchant_id,
                        'type' => MerchantTransaction::TYPE_RETURN_DISCOUNT,
                        'shipment_id' => $shipment->id,
                        'transactionable_type' => Shipment::class,
                        'transactionable_id' => $shipment->id,
                        'country_id' => $shipment->country_id,
                        'state_id' => $shipment->state_id,
                        'amount' => abs($returnDiscount),
                        'description' => "Return fee discount for shipment {$shipment->tracking_no}",
                        'status' => MerchantTransaction::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
                }
            }


            //when fee payer is customer and commison on delivery fees must rebate to merchant
            if ($returnDiscount > 0 && strtolower($shipment->fee_payer ?? '') === 'customer') {
                if (!MerchantTransaction::where('shipment_id', $shipment->id)->where('type', MerchantTransaction::TYPE_DELIVERY_REBATE_TO_MERCHANT)->exists()) {
                    $transactions[] = $this->createTransaction([
                        'merchant_id' => $shipment->merchant_id,
                        'type' => MerchantTransaction::TYPE_DELIVERY_REBATE_TO_MERCHANT,
                        'shipment_id' => $shipment->id,
                        'transactionable_type' => Shipment::class,
                        'transactionable_id' => $shipment->id,
                        'country_id' => $shipment->country_id,
                        'state_id' => $shipment->state_id,
                        'amount' => abs($returnDiscount), // Credit
                        'description' => "Return fee Deducted from Merchant to Consignee for shipment {$shipment->tracking_no}",
                        'status' => MerchantTransaction::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
                }
            }
        });

        return array_filter($transactions);
    }

    /**
     * Record COD collection for a shipment
     * 
     * @param Shipment $shipment
     * @param float|null $amount
     * @return MerchantTransaction|null
     */
    public function recordCOD(Shipment $shipment, ?float $amount = null): ?MerchantTransaction
    {
        if (!$shipment->merchant_id) {
            return null;
        }

        // Idempotency check: Check if COD is already recorded for this shipment
        $existing = MerchantTransaction::where('merchant_id', $shipment->merchant_id)
            ->where('type', MerchantTransaction::TYPE_COD_COLLECTED)
            ->where('shipment_id', $shipment->id)
            ->first();

        if ($existing) {
            \Illuminate\Support\Facades\Log::info("recordCOD: Already recorded for Shipment #{$shipment->id}");
            return $existing;
        }

        // Use getMerchantCOD to get the actual amount for the merchant (excluding customer-paid fees)
        $codAmount = $amount ?? app(\App\Services\CalculationLogicService::class)->getMerchantCOD($shipment);

        if ($codAmount <= 0) {
            return null;
        }

        return $this->createTransaction([
            'merchant_id' => $shipment->merchant_id,
            'type' => MerchantTransaction::TYPE_COD_COLLECTED,
            'shipment_id' => $shipment->id,
            'transactionable_type' => Shipment::class,
            'transactionable_id' => $shipment->id,
            'amount' => $codAmount, // Positive = credit
            'description' => "COD collected for shipment {$shipment->tracking_no}",
            'status' => MerchantTransaction::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Record registration for a shipment (mirrors legacy merchant_created)
     * 
     * @param Shipment $shipment
     * @return MerchantTransaction|null
     */
    public function recordRegistration(Shipment $shipment): ?MerchantTransaction
    {
        if (!$shipment->merchant_id) {
            return null;
        }

        // Idempotency check
        $existing = MerchantTransaction::where('merchant_id', $shipment->merchant_id)
            ->where('type', MerchantTransaction::TYPE_REGISTRATION)
            ->where('shipment_id', $shipment->id)
            ->first();

        if ($existing) {
            \Illuminate\Support\Facades\Log::info("recordRegistration: Already recorded for Shipment #{$shipment->id}");
            return $existing;
        }

        // Mirrors legacy logic: total_cod > 0 ? total_cod : delivery_fee
        $amount = (float)($shipment->total_cod > 0 ? $shipment->total_cod : $shipment->delivery_fee);

        return $this->createTransaction([
            'merchant_id' => $shipment->merchant_id,
            'type' => MerchantTransaction::TYPE_REGISTRATION,
            'shipment_id' => $shipment->id,
            'transactionable_type' => Shipment::class,
            'transactionable_id' => $shipment->id,
            'amount' => $amount,
            'description' => "Shipment registered: {$shipment->tracking_no}",
            'status' => MerchantTransaction::STATUS_PENDING, // Set to PENDING to avoid double-counting balance
        ]);
    }

    /**
     * Record return fees for a shipment
     * 
     * @param Shipment $shipment
     * @return array Array of created transactions
     */
    public function recordReturnFees(Shipment $shipment): array
    {
        if (!$shipment->merchant_id) {
            return [];
        }

        // Idempotency check: if already has return fee/discount, skip
        $anyExisting = MerchantTransaction::where('merchant_id', $shipment->merchant_id)
            ->whereIn('type', [MerchantTransaction::TYPE_RETURN_FEE, MerchantTransaction::TYPE_RETURN_DISCOUNT])
            ->where('shipment_id', $shipment->id)
            ->exists();

        if ($anyExisting) {
            \Illuminate\Support\Facades\Log::info("recordReturnFees: Already recorded for Shipment #{$shipment->id}");
            return [];
        }

        $transactions = [];

        DB::transaction(function () use ($shipment, &$transactions) {
            $baseReturnFee = (float) ($shipment->return_fee_before_discount ?? $shipment->return_fee ?? 0);
            $returnDiscount = (float) ($shipment->return_fee_discount ?? 0);

            if ($baseReturnFee > 0 && strtolower($shipment->fee_payer ?? '') === 'merchant') {
                $transactions[] = $this->createTransaction([
                    'merchant_id' => $shipment->merchant_id,
                    'type' => MerchantTransaction::TYPE_RETURN_FEE,
                    'shipment_id' => $shipment->id,
                    'transactionable_type' => Shipment::class,
                    'transactionable_id' => $shipment->id,
                    'country_id' => $shipment->country_id,
                    'state_id' => $shipment->state_id,
                    'amount' => -1 * abs($baseReturnFee),
                    'base_amount' => $baseReturnFee,
                    'discount_amount' => $returnDiscount,
                    'fee_payer' => $shipment->fee_payer,
                    'description' => "Return fee for shipment {$shipment->tracking_no} (Base)",
                    'status' => MerchantTransaction::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
            }

            if ($returnDiscount > 0 && strtolower($shipment->fee_payer ?? '') === 'merchant') {
                $transactions[] = $this->createTransaction([
                    'merchant_id' => $shipment->merchant_id,
                    'type' => MerchantTransaction::TYPE_RETURN_DISCOUNT,
                    'shipment_id' => $shipment->id,
                    'transactionable_type' => Shipment::class,
                    'transactionable_id' => $shipment->id,
                    'country_id' => $shipment->country_id,
                    'state_id' => $shipment->state_id,
                    'amount' => abs($returnDiscount),
                    'description' => "Return fee discount for shipment {$shipment->tracking_no}",
                    'status' => MerchantTransaction::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
            }
        });

        return array_filter($transactions);
    }


    /**
     * Record pickup task fees
     * 
     * @param MerchantPickupTask $task
     * @param array $fees ['amount' => float, 'discount' => float]
     * @return array Array of created transactions
     */
    public function recordPickupFees(MerchantPickupTask $task, array $fees): array
    {
        if (!$task->merchant_id) {
            return [];
        }

        $transactions = [];
        $baseFee = $fees['amount'] ?? 0;
        $discount = $fees['discount'] ?? 0;
        $netFee = max(0, $baseFee - $discount);

        DB::transaction(function () use ($task, $baseFee, $discount, $netFee, &$transactions) {
            // Record pickup fee (Base amount - Deduction)
            if ($baseFee > 0) {
                // Idempotency check
                if (!MerchantTransaction::where('pickup_task_id', $task->id)->where('type', MerchantTransaction::TYPE_PICKUP_FEE)->exists()) {
                    $transactions[] = $this->createTransaction([
                        'merchant_id' => $task->merchant_id,
                        'type' => MerchantTransaction::TYPE_PICKUP_FEE,
                        'pickup_task_id' => $task->id,
                        'transactionable_type' => MerchantPickupTask::class,
                        'transactionable_id' => $task->id,
                        'reference' => $task->ref ?? null,
                        'amount' => -1 * abs($baseFee), // Deduction
                        'base_amount' => $baseFee,
                        'discount_amount' => $discount,
                        'description' => "Pickup fee for task {$task->ref} (Base)",
                        'status' => MerchantTransaction::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
                }
            }

            // Record pickup discount (Offset credit)
            if ($discount > 0) {
                // Idempotency check
                if (!MerchantTransaction::where('pickup_task_id', $task->id)->where('type', MerchantTransaction::TYPE_PICKUP_DISCOUNT)->exists()) {
                    $transactions[] = $this->createTransaction([
                        'merchant_id' => $task->merchant_id,
                        'type' => MerchantTransaction::TYPE_PICKUP_DISCOUNT,
                        'pickup_task_id' => $task->id,
                        'transactionable_type' => MerchantPickupTask::class,
                        'transactionable_id' => $task->id,
                        'reference' => $task->ref ?? null,
                        'amount' => abs($discount), // Credit
                        'description' => "Pickup fee discount for task {$task->ref}",
                        'status' => MerchantTransaction::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
                }
            }
        });

        return array_filter($transactions);
    }

    /**
     * Record a settlement
     * 
     * @param int $merchantId
     * @param float $amount
     * @param string|null $reference
     * @param array $options Additional options (notes, receipt_path, etc.)
     * @return MerchantTransaction
     */
    public function recordSettlement(int $merchantId, float $amount, ?string $reference = null, array $options = []): MerchantTransaction
    {
        // Idempotency check: use reference (legacy settlement record ID) if provided
        if ($reference) {
            $existing = MerchantTransaction::where('merchant_id', $merchantId)
                ->where('type', MerchantTransaction::TYPE_SETTLEMENT)
                ->where('reference', $reference)
                ->first();

            if ($existing) {
                \Illuminate\Support\Facades\Log::info("recordSettlement: Already recorded for Reference #{$reference}");
                return $existing;
            }
        }

        return $this->createTransaction([
            'merchant_id' => $merchantId,
            'type' => MerchantTransaction::TYPE_SETTLEMENT,
            'reference' => $reference,
            'amount' => -1 * abs($amount), // Negative = payout to merchant
            'description' => $options['description'] ?? "Settlement payment to merchant",
            'paid_by_cash' => $options['paid_by_cash'] ?? null,
            'paid_by_bank' => $options['paid_by_bank'] ?? null,
            'receipt_path' => $options['receipt_path'] ?? null,
            'received_by' => $options['received_by'] ?? null,
            'paid_at' => $options['paid_at'] ?? now(),
            'status' => $options['status'] ?? MerchantTransaction::STATUS_COMPLETED,
            'completed_at' => $options['completed_at'] ?? now(),
            'metadata' => $options['metadata'] ?? null,
        ]);
    }

    /**
     * Record a fine/penalty
     * 
     * @param Shipment $shipment
     * @param float $amount
     * @param string $reason
     * @return MerchantTransaction
     */
    public function recordFine(Shipment $shipment, float $amount, string $reason): MerchantTransaction
    {
        return $this->createTransaction([
            'merchant_id' => $shipment->merchant_id,
            'type' => MerchantTransaction::TYPE_FINE,
            'shipment_id' => $shipment->id,
            'transactionable_type' => Shipment::class,
            'transactionable_id' => $shipment->id,
            'amount' => -1 * abs($amount), // Negative = charge
            'description' => "Fine: {$reason} for shipment {$shipment->tracking_no}",
            'status' => MerchantTransaction::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Record a manual adjustment
     * 
     * @param int $merchantId
     * @param float $amount
     * @param string $reason
     * @param array $options
     * @return MerchantTransaction
     */
    public function recordAdjustment(int $merchantId, float $amount, string $reason, array $options = []): MerchantTransaction
    {
        return $this->createTransaction([
            'merchant_id' => $merchantId,
            'type' => MerchantTransaction::TYPE_ADJUSTMENT,
            'amount' => $amount, // Can be positive or negative
            'description' => $reason,
            'reference' => $options['reference'] ?? null,
            'shipment_id' => $options['shipment_id'] ?? null,
            'status' => MerchantTransaction::STATUS_COMPLETED,
            'completed_at' => now(),
            'metadata' => $options['metadata'] ?? null,
        ]);
    }

    /**
     * Record a pickup deposit (amount received by driver)
     * 
     * @param MerchantPickupTask $task
     * @param float $amount
     * @return MerchantTransaction
     */
    public function recordPickupDeposit(PickuptaskTransaction $ptt): MerchantTransaction
    {
        return $this->createTransaction([
            'merchant_id' => $ptt->pickuptask->merchant_id,
            'type' => MerchantTransaction::TYPE_PICKUP_DEPOSIT,
            'pickup_task_id' => $ptt->pickuptask->id,
            'transactionable_type' => PickuptaskTransaction::class,
            'transactionable_id' => $ptt->id,
            'reference' => $ptt->pickuptask->ref ?? null,
            'amount' => $ptt->amount, // Positive = credit to merchant (it's a deposit of funds collected/received)
            'paid_by_bank' => $ptt->paid_by_bank,
            'paid_by_cash' => $ptt->paid_by_cash,
            'description' => "Pickup deposit for task {$ptt->pickuptask->ref}",
            'status' => MerchantTransaction::STATUS_COMPLETED,
            'completed_at' => now(),

        ]);
    }

    /**
     * Get merchant balance
     * 
     * @param int $merchantId
     * @return float
     */
    public function getBalance(int $merchantId): float
    {
        return (float) MerchantTransaction::forMerchant($merchantId)
            ->completed()
            ->sum('amount');
    }

    /**
     * Get merchant balance for specific period
     * 
     * @param int $merchantId
     * @param string $from
     * @param string $to
     * @return array
     */
    public function getBalanceForPeriod(int $merchantId, string $from, string $to): array
    {
        $transactions = MerchantTransaction::forMerchant($merchantId)
            ->completed()
            ->dateRange($from, $to)
            ->get();

        return [
            'total' => $transactions->sum('amount'),
            'credits' => $transactions->where('amount', '>', 0)->sum('amount'),
            'debits' => abs($transactions->where('amount', '<', 0)->sum('amount')),
            'fees' => abs($transactions->whereIn('type', [
                MerchantTransaction::TYPE_DELIVERY_FEE,
                MerchantTransaction::TYPE_RETURN_FEE,
                MerchantTransaction::TYPE_PICKUP_FEE,
            ])->sum('amount')),
            'discounts' => $transactions->whereIn('type', [
                MerchantTransaction::TYPE_DELIVERY_DISCOUNT,
                MerchantTransaction::TYPE_RETURN_DISCOUNT,
                MerchantTransaction::TYPE_PICKUP_DISCOUNT,
            ])->sum('amount'),
            'cod' => $transactions->where('type', MerchantTransaction::TYPE_COD_COLLECTED)->sum('amount'),
            'settlements' => abs($transactions->where('type', MerchantTransaction::TYPE_SETTLEMENT)->sum('amount')),
            'pickup_deposit' => $transactions->where('type', MerchantTransaction::TYPE_PICKUP_DEPOSIT)->sum('amount'),
        ];
    }

    /**
     * Create a transaction record
     * 
     * @param array $data
     * @return MerchantTransaction
     */
    private function createTransaction(array $data): MerchantTransaction
    {
        if (!isset($data['created_by']) && Auth::check()) {
            $data['created_by'] = Auth::id();
        }

        $lookup = [
            'merchant_id' => $data['merchant_id'],
            'transactionable_id' => $data['transactionable_id'] ?? null,
            'transactionable_type' => $data['transactionable_type'] ?? null,
            'type' => $data['type'],
        ];

        $attributes = [
            'amount' => $data['amount'] ?? null,
            'base_amount' => $data['base_amount'] ?? null,
            'completed_at' => $data['completed_at'] ?? null,
            'country_id' => $data['country_id'] ?? null,
            'created_at' => $data['created_at'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'currency' => $data['currency'] ?? 'OMR',
            'deleted_at' => $data['deleted_at'] ?? null,
            'description' => $data['description'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'fee_payer' => $data['fee_payer'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'paid_at' => $data['paid_at'] ?? null,
            'paid_by_bank' => $data['paid_by_bank'] ?? null,
            'paid_by_cash' => $data['paid_by_cash'] ?? null,
            'pickup_task_id' => $data['pickup_task_id'] ?? null,
            'receipt_path' => $data['receipt_path'] ?? null,
            'received_by' => $data['received_by'] ?? null,
            'reference' => $data['reference'] ?? null,
            'shipment_id' => $data['shipment_id'] ?? null,
            'state_id' => $data['state_id'] ?? null,
            'status' => $data['status'] ?? 'pending',
        ];

        try {
            if ($data['type'] === 'settlement') {
                // Always create a new record
                $transaction = MerchantTransaction::create(
                    array_merge($lookup, $attributes)
                );
            } else {
                // Update or create for all other types
                $transaction = MerchantTransaction::updateOrCreate(
                    $lookup,
                    $attributes
                );
            }

            \Log::info('MerchantTransaction saved', [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'merchant_id' => $transaction->merchant_id,
            ]);

            return $transaction;

        } catch (\Exception $e) {
            \Log::error('Failed to save MerchantTransaction', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }


}
