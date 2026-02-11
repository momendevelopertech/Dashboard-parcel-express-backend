@extends('layouts.app')

@section('content')
<div class="invoice-container">
    <h2>Pending Invoice #{{ $invoice->id }}</h2>

    <div class="header">
        <div class="driver-info">
            <h4>{{ $driver->name }}</h4>
            <p>License: {{ $driver->license_number }}</p>
        </div>

        <div class="dates">
            <p>Issue Date: {{ $invoice->created_at->format('d/m/Y') }}</p>
        </div>
    </div>

    <table class="shipments-table">
        <thead>
            <tr>
                <th>Tracking No</th>
                <th>Shipment Date</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->invoice_shipments as $shipment)
            <tr>
                <td>{{ $shipment->shipment_tracking_no }}</td>
                <td>{{ $shipment->shipment->created_at->format('d/m/Y') }}</td>
                <td>{{ number_format($shipment->amount, 2) }}</td>
                <td>{{ $shipment->status }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-section">
        <h3>Total Amount: {{ number_format($invoice->amount, 2) }}</h3>
    </div>

    <a href="{{ route('invoices.print-driver') }}" class="btn btn-print">
        Print Invoice
    </a>
</div>
@endsection