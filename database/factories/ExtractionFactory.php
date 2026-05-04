<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Extraction>
 */
class ExtractionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quarry_id' => \App\Models\Quarry::factory(),
            'operator_id' => \App\Models\User::factory()->create(['role' => 'operator']),
            'extraction_date' => fake()->date(),
            'extraction_time' => fake()->time(),
            'quantity' => fake()->randomFloat(2, 1, 1000),
            'unit' => 'cubic meters',
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
