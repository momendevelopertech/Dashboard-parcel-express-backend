<?php

namespace Database\Factories;

use App\Models\ShipmentInformation;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShipmentInfo>
 */
class ShipmentInfoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = ShipmentInformation::class;

    public function definition(): array
    {
        return [
            'zone_id'     => Zone::inRandomShipment()->first()->id,
            'weight'     => $this->faker->randomFloat(2, 0.1, 10),
            'length'     => $this->faker->numberBetween(10, 100),
            'width'      => $this->faker->numberBetween(10, 100),
            'height'     => $this->faker->numberBetween(5, 50),
        ];
    }
}
