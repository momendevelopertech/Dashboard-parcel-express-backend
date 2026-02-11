<?php

namespace Database\Factories;

use App\Models\ShipmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentItemFactory extends Factory
{
    protected $model = ShipmentItem::class;

    public function definition(): array
    {
        return [
            'quantity'  => $this->faker->numberBetween(1, 5),
            'price'     => $this->faker->randomFloat(2, 5, 200),
            'name'      => $this->faker->word,
            'weight'    => $this->faker->randomFloat(2, 0.1, 10),
            'fullName'  => $this->faker->words(3, true),
            'category'  => $this->faker->word,
            'code'      => strtoupper($this->faker->bothify('??###')),
        ];
    }
}
