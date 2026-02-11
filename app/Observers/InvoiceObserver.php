<?php

namespace App\Observers;

use App\Models\Invoice;
use Carbon\Carbon;

class InvoiceObserver
{
    public function creating(Invoice $invoice)
    {
        $facility = facility();

        if ($facility) {
            $invoice->owner_type = $facility->type;
            $invoice->owner_id = $facility->id;
        }
    }

    public function created(Invoice $invoice)
    {
        $date = Carbon::parse($invoice->issue_date);
        $invoice->invoice_no = $date->format('dm')
            . $invoice->id
            . $invoice->invoiceable_id;

        $invoice->saveQuietly();
    }
}
