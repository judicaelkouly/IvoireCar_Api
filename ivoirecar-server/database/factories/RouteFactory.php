<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'departure_station_id' => Station::factory(),
            'arrival_station_id' => Station::factory(),
            'distance_km' => $this->faker->numberBetween(150, 650),
            'estimated_duration_minutes' => $this->faker->numberBetween(120, 480),
            'base_price' => $this->faker->randomElement([3000, 5000, 7000, 10000]),
        ];
    }
}
