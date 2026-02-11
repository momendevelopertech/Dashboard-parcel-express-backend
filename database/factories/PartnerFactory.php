<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company . ' Integration',
            'contact_email' => $this->faker->unique()->companyEmail,
            'allowed_scopes' => ['read:shipments', 'tracking:read'],
            'is_active' => true,
            'rate_limit' => 120,
            'daily_quota' => 150000,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withHighLimits(): self
    {
        return $this->state(fn(array $attributes) => [
            'rate_limit' => 500,
            'daily_quota' => 1000000,
        ]);
    }
}