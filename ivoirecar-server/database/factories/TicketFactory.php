<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'user_id' => User::factory(),
            'ticket_number' => 'TCK-' . strtoupper(Str::random(8)),
            'passenger_name' => $this->faker->name(),
            'passenger_phone' => '+225 07' . $this->faker->numerify('########'),
            'seat_number' => $this->faker->numberBetween(1, 50),
            'price' => 5000,
            'status' => $this->faker->randomElement(['reserved', 'paid', 'boarded', 'cancelled']),
            'payment_status' => 'completed',
        ];
    }
}
