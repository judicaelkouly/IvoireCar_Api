<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ParcelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'sender_id' => User::factory(),
            'departure_station_id' => Station::factory(),
            'destination_station_id' => Station::factory(),
            'tracking_number' => 'PAR-' . strtoupper(Str::random(10)),
            'sender_name' => $this->faker->name(),
            'sender_phone' => '+225 07' . $this->faker->numerify('########'),
            'recipient_name' => $this->faker->name(),
            'recipient_phone' => '+225 01' . $this->faker->numerify('########'),
            'weight_kg' => $this->faker->randomFloat(2, 0.5, 25.0),
            'shipping_cost' => $this->faker->randomElement([2000, 3500, 5000, 8000]),
            'status' => $this->faker->randomElement(['pending', 'in_transit', 'delivered']),
            'payment_status' => 'completed',
        ];
    }
}

