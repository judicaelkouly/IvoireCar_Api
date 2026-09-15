<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'registration_number' => strtoupper($this->faker->bothify('???-###-??')),
            'model' => $this->faker->randomElement(['Toyota Coaster', 'Mercedes-Benz Sprinter', 'Isuzu Bus', 'Scania Touring']),
            'capacity' => $this->faker->randomElement([30, 50, 70]),
            'status' => 'available',
        ];
    }
}
