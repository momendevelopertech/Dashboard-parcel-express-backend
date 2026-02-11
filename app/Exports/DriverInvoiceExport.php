<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DriverInvoiceExport implements FromCollection, WithHeadings
{
    protected $invoices;
    protected $columns;

    public function __construct($invoices, $columns = null)
    {
        $this->invoices = $invoices;
        $this->columns = $columns ?: $this->getDefaultColumns();
    }

    public function collection()
    {
        return $this->invoices->map(function ($invoice) {
            $data = [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no ?? '',
                'statement_date' => $invoice->created_at ? $invoice->created_at->format('Y-m-d') : '',
                'statement_time' => $invoice->created_at ? $invoice->created_at->format('H:i:s') : '',
                'hub' => $invoice->owner?->name ?? '',
                'company' => $invoice->invoiceable?->driver?->company?->name ?? '',
                'driver' => $invoice->invoiceable?->name ?? '',
                'driver_phone' => $invoice->invoiceable?->phone ?? '',
                'runsheet_id' => $invoice->runsheet?->id ?? '',
                'amount' => number_format($invoice->driver_total_commission ?? 0, 2),
                'total_amount' => number_format($invoice->total_amount ?? 0, 2),
                'paid_to_driver' => number_format($invoice->paid_to_driver ?? 0, 2),
                'status' => $invoice->status ?? '',
                'fee_type' => $invoice->fee_type ?? '',
                'calculation' => $invoice->calculation ?? '',
                'created_at' => $invoice->created_at ? $invoice->created_at->format('Y-m-d H:i:s') : '',
                'updated_at' => $invoice->updated_at ? $invoice->updated_at->format('Y-m-d H:i:s') : '',
            ];

            return collect($this->columns)->mapWithKeys(function ($column) use ($data) {
                return [$column => $data[$column] ?? ''];
            });
        });
    }

    public function headings(): array
    {
        return collect($this->columns)->map(function ($column) {
            return match ($column) {
                'id' => 'Invoice ID',
                'invoice_no' => 'Statement ID',
                'statement_date' => 'Statement Date',
                'statement_time' => 'Statement Time',
                'hub' => 'Hub',
                'company' => 'Company',
                'driver' => 'Driver Name',
                'driver_phone' => 'Driver Phone',
                'runsheet_id' => 'Runsheet ID',
                'amount' => 'Commission Amount',
                'total_amount' => 'Total Amount',
                'paid_to_driver' => 'Paid to Driver',
                'status' => 'Status',
                'fee_type' => 'Fee Type',
                'calculation' => 'Calculation',
                'created_at' => 'Created At',
                'updated_at' => 'Updated At',
                default => ucfirst(str_replace('_', ' ', $column)),
            };
        })->toArray();
    }

    private function getDefaultColumns()
    {
        return [
            'id',
            'invoice_no',
            'statement_date',
            'hub',
            'company',
            'driver',
            'runsheet_id',
            'amount',
            'status',
        ];
    }
}
