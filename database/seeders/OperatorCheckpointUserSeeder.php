<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OperatorCheckpointUserSeeder extends Seeder
{
    /**
     * Seed sample operators and checkpoint users for testing.
     */
    public function run(): void
    {
        // Create Operators
        User::create([
            'username' => 'operator1',
            'email' => 'operator1@geosand.local',
            'password' => Hash::make('password123'),
            'role' => 'operator',
            'is_active' => true,
        ]);

        User::create([
            'username' => 'operator2',
            'email' => 'operator2@geosand.local',
            'password' => Hash::make('password123'),
            'role' => 'operator',
            'is_active' => true,
        ]);

        User::create([
            'username' => 'operator3',
            'email' => 'operator3@geosand.local',
            'password' => Hash::make('password123'),
            'role' => 'operator',
            'is_active' => true,
        ]);

        // Create Checkpoint Users
        User::create([
            'username' => 'checkpoint1',
            'email' => 'checkpoint1@geosand.local',
            'password' => Hash::make('password123'),
            'role' => 'checkpoint',
            'is_active' => true,
        ]);

        User::create([
            'username' => 'checkpoint2',
            'email' => 'checkpoint2@geosand.local',
            'password' => Hash::make('password123'),
            'role' => 'checkpoint',
            'is_active' => true,
        ]);

        // Create an inactive operator for testing
        User::create([
            'username' => 'operator_inactive',
            'email' => 'operator.inactive@geosand.local',
            'password' => Hash::make('password123'),
            'role' => 'operator',
            'is_active' => false,
        ]);
    }
}
