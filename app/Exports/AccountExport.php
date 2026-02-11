<?php

namespace App\Exports;

use App\Models\Account;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AccountExport implements FromCollection, WithHeadings
{
    protected $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function collection()
    {
        $rows = [];

        $rows[] = [
            'Account ID' => $this->account->id,
            'Name' => $this->account->accountable->name,
            'Contact Number' => $this->account->accountable->contact_number,
            // 'Accountable Type' => class_basename($this->account->accountable_type),
            'Parcel Value' => $this->account->parcel_value,
            'Cash Balance' => $this->account->cash_balance,
            'Paid Balance' => $this->account->paid_balance,
            'Transaction ID' => 'ACCOUNT SUMMARY',
            'Direction' => '',
            'Type' => '',
            'Amount' => '',
            'From' => '',
            'To' => '',
            'Shipment Tracking#' => '',
            'Shipment Value' => '',
            'Transaction Date' => ''
        ];

        foreach ($this->account->sent_transactions as $transaction) {
            $rows[] = $this->formatTransactionRow($transaction, 'Sent');
        }

        foreach ($this->account->received_transactions as $transaction) {
            $rows[] = $this->formatTransactionRow($transaction, 'Received');
        }

        return collect($rows);
    }

    protected function formatTransactionRow($transaction, $direction)
    {
        return [
            'Account ID' => $this->account->id,
            'Name' => $this->account->accountable->name,
            'Contact Number' => $this->account->accountable->contact_number,
            // 'Accountable Type' => '',
            'Parcel Value' => '',
            'Cash Balance' => '',
            'Paid Balance' => '',
            'Transaction ID' => $transaction->id,
            'Direction' => $direction,
            'Type' => $transaction->type,
            'Amount' => $transaction->amount,
            'From' => $this->formatParticipant($transaction->from),
            'To' => $this->formatParticipant($transaction->to),
            'Shipment Tracking#' => $transaction->shipment->tracking_no ?? 'N/A',
            'Shipment Value' => $transaction->shipment->value ?? 'N/A',
            'Transaction Date' => $transaction->created_at->format('Y-m-d H:i')
        ];
    }

    protected function formatParticipant($participant)
    {
        if (!$participant) return 'N/A';

        return sprintf(
            "%s #%s: %s",
            class_basename($participant),
            $participant->id,
            $participant->name ?? $participant->email
        );
    }

    public function headings(): array
    {
        return [
            'Account ID',
            'Name',
            'Contact',
            'Parcel Value',
            'Cash Balance',
            'Paid Balance',
            'Transaction ID',
            'Direction',
            'Type',
            'Amount',
            'From',
            'To',
            'Shipment Tracking#',
            'Shipment Value',
            'Transaction Date'
        ];
    }
}
