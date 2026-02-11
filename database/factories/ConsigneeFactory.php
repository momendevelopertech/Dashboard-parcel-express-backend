<?php

namespace Database\Factories;

use App\Models\Consignee;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Place;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Consignee>
 */
class ConsigneeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = Consignee::class;

    public function definition(): array
    {
        return [
            'name'           => $this->faker->name,
            'email'          => $this->faker->safeEmail,
            'cellphone'      => $this->faker->phoneNumber,
            'district'       => $this->faker->city,
            'country_id'      => 165,
            'governorate_id'  => Governorate::inRandomShipment()->first()->id,
            'state_id'        => State::inRandomShipment()->first()->id,
            'place_id'        => Place::inRandomShipment()->first()->id,
            'zipcode'        => $this->faker->postcode,
            'streetAddress'  => $this->faker->streetAddress,
        ];
    }
}
