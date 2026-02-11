<?php

namespace App\Services;

use App\Models\DriverBonusesTransaction;
use App\Models\Transaction;
use App\Models\Hub;
use App\Models\User;

class DriverService
{
    /**
     * Calculate total due/credit amount for a driver
     */
    public function calculateSettlementDue(User $driver, $from = null, $to = null, $isPaid = null): float
    {
        // Bonuses
        $bonuses = DriverBonusesTransaction::query()
            ->where('driver_id', $driver->id)
            ->where('status', 'delivered')
            ->when($isPaid !== null, fn ($q) => $q->where('isPaid', $isPaid))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->sum('bonus_amount');

        // Base transaction query
        $baseTransaction = Transaction::query()
            ->where('to_id', $driver->id)
            ->when($isPaid !== null, fn ($q) => $q->where('isPaid', $isPaid))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $penaltiesAndDeductions = (float) (clone $baseTransaction)
            ->where('to_type', User::class)
            ->where('type', 'fine')
            ->sum('amount');

        $advance = (float) (clone $baseTransaction)
            ->where('from_type', Hub::class)
            ->where('type', 'advance')
            ->sum('amount');

        $settlements = (float) (clone $baseTransaction)
            ->where('from_type', Hub::class)
            ->where('type', 'payout')
            ->sum('amount');

        return (float) ($bonuses - ($penaltiesAndDeductions + $advance) - $settlements);
    }

}
