<?php

namespace Database\Factories;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Merchant>
 */
class MerchantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Merchant::class;

    public function definition(): array
    {
        return [
            'country_id'              => 165,
            'governorate_id'          => \App\Models\Governorate::inRandomShipment()->first()->id,
            'state_id'                => \App\Models\State::inRandomShipment()->first()->id,
            'place_id'                => \App\Models\Place::inRandomShipment()->first()->id,
            'user_id'                 => \App\Models\User::role('merchant')->inRandomShipment()->first()->id,
            'address'                => $this->faker->address,
            'contact_no'              => $this->faker->phoneNumber,
            'lat'                    => $this->faker->latitude,
            'lng'                    => $this->faker->longitude,
            'currency'               => $this->faker->currencyCode,
            'facility_to_facility_fees' => $this->faker->randomFloat(2, 0, 50),
            'owner_id'               => \App\Models\User::inRandomShipment()->first()->id,
            'owner_type'             => \App\Models\User::class,
        ];
    }
}
