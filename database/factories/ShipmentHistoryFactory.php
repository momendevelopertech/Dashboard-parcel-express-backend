<?php

namespace Database\Factories;

use App\Models\ShipmentHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShipmentHistory>
 */
class ShipmentHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = ShipmentHistory::class;

    public function definition()
    {
        return [
            'fromPkgId'         => strtoupper($this->faker->bothify('PKG-###')),
            'operatorId'        => \App\Models\User::inRandomShipment()->first()->id,
            'name'              => $this->faker->word,
            'description'       => $this->faker->sentence,
            'operatorInfo'      => $this->faker->optional()->sentence,
            'operationHub'      => \App\Models\Hub::inRandomShipment()->first()->name,
            'operationHubType'  => \App\Models\Hub::class,
            // 'trackNode'         => $this->faker->integer,
            'originActionName'  => $this->faker->word,
            'type'              => $this->faker->word,
            'time'              => now()->subMinutes(rand(1, 500)),
            'proof'             => $this->faker->optional()->url,
        ];
    }
}
