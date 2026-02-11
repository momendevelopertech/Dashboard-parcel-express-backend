<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\Consignee;
use App\Models\Shipment;
use App\Models\ShipmentDelivery;
use App\Models\ShipmentHistory;
use App\Models\ShipmentInformation;
use App\Models\ShipmentItem;
use App\Models\Shipper;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition()
    {
        return [
            'shipper_id'    => Shipper::factory(),
            'merchant_id'     => Merchant::factory(),
            'consignee_id'  => Consignee::factory(),
            'created_by'    => User::factory(),

            'tracking_no'   => generate_tracking_no(),
            'value'         => $this->faker->randomFloat(2, 10, 200),
            'delivery_fee'  => $this->faker->randomFloat(2, 5, 50),
            'amount'        => function (array $attrs) {
                return $attrs['value'] + $attrs['delivery_fee'];
            },
            'status'        => 'CREATED',
            'in_exception'  => false,
            'driver_id'     => null,
            'owner_type'    => 'App/Models/Station',
            'owner_id'      => 3,
            'in_warehouse'  => true,
            'payment_type'  => $this->faker->randomElement(['cash', 'card']),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Shipment $shipment) {
            ShipmentInformation::factory()
                ->for($shipment, 'shipment')
                ->create();

            ShipmentItem::factory()
                ->count(rand(1, 5))
                ->for($shipment, 'shipment')
                ->create();

            ShipmentDelivery::factory()
                ->for($shipment, 'shipment')
                ->create();

            ShipmentHistory::factory()
                ->count(10)
                ->for($shipment, 'shipment')
                ->create();
        });
    }
}
