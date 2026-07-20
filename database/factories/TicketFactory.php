<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'TKT-'.$this->faker->unique()->numerify('######'),
            'requester_name' => $this->faker->name(),
            'requester_email' => $this->faker->unique()->safeEmail(),
            'subject' => $this->faker->sentence(),
            'assignee' => $this->faker->boolean(70) ? User::factory()->agent()->create()->id : null,
            'status' => $this->faker->randomElement(['open', 'in_progress', 'resolved', 'closed']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'channel' => $this->faker->randomElement(['email', 'phone', 'chat', 'in-person']),
            'customer_access_token' => Str::random(64),
        ];
    }
}
