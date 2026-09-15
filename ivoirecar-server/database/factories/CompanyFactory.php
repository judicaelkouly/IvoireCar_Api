<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company() . ' Express',
            'email' => $this->faker->unique()->companyEmail(),
            'slug' => Str::slug($name),
            'phone' => '+225 07' . $this->faker->numerify('########'),
            'address' => $this->faker->streetAddress(),
        ];
    }
}
