<?php

namespace App\Support\Sequences;

use App\Models\FinancialRequest;

class FinancialRequestCode
{
    public static function next(): string
    {
        $year = now()->year;
        $last = FinancialRequest::whereYear('created_at', $year)
            ->orderByDesc('id')->value('code');

        $seq = 0;
        if ($last && preg_match('/REQ-' . $year . '-(\d+)/', $last, $m)) {
            $seq = (int) $m[1];
        }
        return sprintf('REQ-%d-%05d', $year, $seq + 1);
    }
}
