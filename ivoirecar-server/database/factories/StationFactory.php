<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class StationFactory extends Factory
{
    public function definition(): array
    {
        $cities = ['Abidjan (Adjamé)', 'Bouaké', 'Yamoussoukro', 'San-Pédro', 'Korhogo', 'Man', 'Daloa'];

        return [
            'company_id' => Company::factory(),
            'name' => 'Gare Principal de ' . $this->faker->randomElement($cities),
            'city' => $this->faker->randomElement($cities),
            'address' => $this->faker->address(),
            'phone' => '+225 05' . $this->faker->numerify('########'),
        ];
    }
}
