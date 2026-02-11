<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CODCollectionExport implements FromCollection, WithHeadings
{
    protected $runsheets;
    protected $columns;

    public function __construct($runsheets, $columns = null)
    {
        $this->runsheets = $runsheets;
        $this->columns = $columns ?: $this->getDefaultColumns();
    }

    public function collection()
    {
        return $this->runsheets->map(function ($runsheet) {
            $signedParcels = $runsheet->delivered_shipments_count ?? 0;
            $totalParcels = $runsheet->assigned_shipments_count ?? 0;
            $returnedParcels = $runsheet->returned_shipments_count ?? 0;
            $differenceParcels = $runsheet->difference_shipments_count ?? 0;
            $notSignedParcels = $runsheet->not_delivered_shipments_count ?? 0;

            // Calculate signed money
            $signedMoney = [
                'cash' => $runsheet->delivered_shipments
                    ->filter(function ($shipment) {
                        return $shipment->payment_method === 'cod';
                    })
                    ->sum('amount') ?? 0,
                'pos' => $runsheet->delivered_shipments
                    ->filter(function ($shipment) {
                        return $shipment->payment_method === 'paid';
                    })
                    ->sum('amount') ?? 0,
            ];
            $signedMoney['total'] = $signedMoney['cash'] + $signedMoney['pos'];

            $data = [
                'id' => $runsheet->id,
                'company' => $runsheet->driver?->driver?->company?->name ?? 'N/A',
                'driver' => $runsheet->driver?->name ?? 'N/A',
                'driver_phone' => $runsheet->driver?->phone ?? 'N/A',
                'receive_date' => $runsheet->created_at ? $runsheet->created_at->format('Y-m-d') : '',
                'create_time' => $runsheet->created_at ? $runsheet->created_at->format('H:i:s') : '',
                'confirmed_date' => $runsheet->confirmed_at ? Carbon::parse($runsheet->confirmed_at)->format('Y-m-d') : '',
                'confirmed_time' => $runsheet->confirmed_at ? carbon::parse($runsheet->confirmed_at)->format('H:i:s') : '',
                'status' => $runsheet->status,
                'total_parcels' => $totalParcels,
                'to_sign_parcels' => $totalParcels - $signedParcels,
                'signed_parcels' => $signedParcels,
                'holding_parcels' => $runsheet->holding_shipments_count ?? 0,
                'not_signed_parcels' => $notSignedParcels,
                'returned_parcels' => $returnedParcels,
                'dto_parcels' => $runsheet->dto_to_return ?? 0,
                'difference_parcels' => $differenceParcels,
                'signed_money_cash' => number_format($signedMoney['cash'], 2),
                'signed_money_pos' => number_format($signedMoney['pos'], 2),
                'signed_money_total' => number_format($signedMoney['total'], 2),
                'collection_money_cash' => number_format($runsheet->submission?->paid_by_cash ?? 0, 2),
                'collection_money_pos' => number_format($runsheet->submission?->paid_by_bank ?? 0, 2),
                'collection_money_total' => number_format(($runsheet->submission?->paid_by_cash ?? 0) + ($runsheet->submission?->paid_by_bank ?? 0), 2),
                'collection_difference' => number_format((($runsheet->submission?->paid_by_cash ?? 0) + ($runsheet->submission?->paid_by_bank ?? 0)) - $signedMoney['total'], 2),
                'hold_reason' => $runsheet->submission?->notes ?? '',
                'total_amount' => number_format($runsheet->submission?->total_amount ?? 0, 2),
                'received_by' => $runsheet->submission?->receivedBy?->name ?? '',
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
                'id' => 'Runsheet ID',
                'company' => 'Company',
                'driver' => 'Driver Name',
                'driver_phone' => 'Driver Phone',
                'receive_date' => 'Receive Date',
                'create_time' => 'Create Time',
                'confirmed_date' => 'Confirmed Date',
                'confirmed_time' => 'Confirmed Time',
                'status' => 'Status',
                'total_parcels' => 'Total Parcels',
                'to_sign_parcels' => 'To Sign Parcels',
                'signed_parcels' => 'Signed Parcels',
                'holding_parcels' => 'Holding Parcels',
                'not_signed_parcels' => 'Not Signed Parcels',
                'returned_parcels' => 'Returned Parcels',
                'dto_parcels' => 'DTO Parcels',
                'difference_parcels' => 'Difference Parcels',
                'signed_money_cash' => 'Signed Money (Cash)',
                'signed_money_pos' => 'Signed Money (POS)',
                'signed_money_total' => 'Signed Money (Total)',
                'collection_money_cash' => 'Collection Money (Cash)',
                'collection_money_pos' => 'Collection Money (POS)',
                'collection_money_total' => 'Collection Money (Total)',
                'collection_difference' => 'Collection Difference',
                'hold_reason' => 'Hold Reason',
                'total_amount' => 'Total Amount',
                'received_by' => 'Received By',
                default => ucfirst(str_replace('_', ' ', $column)),
            };
        })->toArray();
    }

    private function getDefaultColumns()
    {
        return [
            'id',
            'company',
            'driver',
            'driver_phone',
            'receive_date',
            'create_time',
            'status',
            'total_parcels',
            'signed_parcels',
            'holding_parcels',
            'not_signed_parcels',
            'returned_parcels',
            'difference_parcels',
            'signed_money_cash',
            'signed_money_pos',
            'signed_money_total',
            'collection_money_cash',
            'collection_money_pos',
            'collection_money_total',
            'collection_difference',
        ];
    }
}
