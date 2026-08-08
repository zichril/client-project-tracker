<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+1 month');

        return [
            'client_name' => $this->faker->company(),
            'project_name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['Planning', 'In Progress', 'On Hold', 'Completed']),
            'priority' => $this->faker->randomElement(['Low', 'Medium', 'High']),
            'start_date' => $startDate->format('Y-m-d'),
            'due_date' => $this->faker->dateTimeBetween($startDate, '+3 months')->format('Y-m-d'),
        ];
    }
}
