<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Place;
use App\Models\Shipper;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shipper>
 */
class ShipperFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = Shipper::class;

    public function definition(): array
    {
        return [
            'name'           => $this->faker->company,
            'email'          => $this->faker->unique()->companyEmail,
            'contact'        => $this->faker->phoneNumber,
            'country_id'      => 165,
            'governorate_id'  => Governorate::inRandomShipment()->first()->id,
            'state_id'        => State::inRandomShipment()->first()->id,
            'place_id'        => Place::inRandomShipment()->first()->id,
            // 'city_id'         => City::inRandomShipment()->first()->id,
            'address'        => $this->faker->streetAddress,
            'zip_code'        => $this->faker->postcode,
            'website'        => $this->faker->optional()->url,
            'notes'          => $this->faker->optional()->sentence,
            'is_active'       => true,
            'owner_id'       => User::inRandomShipment()->first()->id,
            'owner_type'     => User::class,
        ];
    }
}
