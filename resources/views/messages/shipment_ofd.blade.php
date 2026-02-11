<div class="shipment-notification">
    <h2>Your Shipment is Out for Delivery! 🚚</h2>
    <p>Shipment Tracking Number: <strong>{{ $shipment->tracking_no }}</strong></p>

    <div class="driver-info">
        <h3>Driver Details:</h3>
        <p>Name: {{ $driver->name }}</p>
        <p>Name: {{ $driver->driver->phone }}</p>
        <p>Contact: <a href="tel:{{ $driver->phone }}">{{ $driver->consignee->cellphone }}</a></p>
    </div>
</div>