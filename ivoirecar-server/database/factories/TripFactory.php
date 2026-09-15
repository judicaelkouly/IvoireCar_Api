<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Route;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripFactory extends Factory
{
    public function definition(): array
    {
        $departure = $this->faker->dateTimeBetween('now', '+7 days');
        $capacity = $this->faker->randomElement([30, 50, 70]);

        return [
            'company_id' => Company::factory(),
            'route_id' => Route::factory(),
            'vehicle_id' => Vehicle::factory(),
            'departure_time' => $departure,
            'arrival_time' => (clone $departure)->modify('+4 hours'),
            'price' => $this->faker->randomElement([4000, 6000, 8000, 12000]),
            'total_seats' => $capacity,
            'available_seats' => $capacity,
            'status' => 'scheduled',
        ];
    }
}
