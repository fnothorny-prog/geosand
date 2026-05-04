<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quarry>
 */
class QuarryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Bongabong, Oriental Mindoro coordinates range
        // Approximate bounds: 12.6° to 12.8° N, 121.3° to 121.5° E
        return [
            'name' => fake()->company() . ' Quarry',
            'latitude' => fake()->randomFloat(8, 12.6, 12.8),
            'longitude' => fake()->randomFloat(8, 121.3, 121.5),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
