<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'paymentable_type' => Ticket::class,
            'paymentable_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'amount' => $this->faker->randomElement([3000, 5000, 7000]),
            'payment_method' => $this->faker->randomElement(['cash', 'mobile_money', 'card']),
            'provider' => $this->faker->randomElement(['Wave', 'Orange Money', 'MTN MoMo', 'Moov Money']),
            'transaction_reference' => 'PAY-' . strtoupper(Str::random(10)),
            'status' => 'completed',
        ];
    }
}
