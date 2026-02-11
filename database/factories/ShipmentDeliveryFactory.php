<?php

namespace Database\Factories;

use App\Models\ShipmentDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShipmentDelivery>
 */
class ShipmentDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = ShipmentDelivery::class;

    public function definition()
    {
        return [
            'ofd_count'           => $this->faker->numberBetween(0, 3),
            'status'             => $this->faker->randomElement(['NOTDELIVERED', 'DELIVERED', 'RETURNED']),
            'driver_call_count'    => $this->faker->numberBetween(0, 5),
            'future_delivery_date' => $this->faker->dateTimeBetween('now', '+7 days')->format('Y-m-d'),
            'payment_bank_transfer' => $this->faker->randomFloat(2, 0, 100),
            'payment_cash'        => $this->faker->randomFloat(2, 0, 100),
            'proof'              => $this->faker->optional()->url,
            'delivery_lat'        => $this->faker->latitude,
            'delivery_lng'        => $this->faker->longitude,
            'note'               => $this->faker->optional()->sentence,
        ];
    }
}
