<?php

namespace Database\Seeders;

use App\Models\Quarry;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuarrySeeder extends Seeder
{
    /**
     * Seed sample quarries in Bongabong area for testing.
     */
    public function run(): void
    {
        // Get admin user as creator
        $admin = User::where('role', 'admin')->first();

        // Create active quarries in Bongabong, Oriental Mindoro
        // Bongabong coordinates: approximately 12.7° N, 121.4° E
        
        Quarry::create([
            'name' => 'Riverside Sand Quarry',
            'latitude' => 12.7123,
            'longitude' => 121.4056,
            'description' => 'Main quarry site along the river bank',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        Quarry::create([
            'name' => 'Mountain View Quarry',
            'latitude' => 12.7345,
            'longitude' => 121.4234,
            'description' => 'Quarry site near the mountain base',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        Quarry::create([
            'name' => 'Central Bongabong Quarry',
            'latitude' => 12.7089,
            'longitude' => 121.4178,
            'description' => 'Central location quarry site',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        Quarry::create([
            'name' => 'East Valley Quarry',
            'latitude' => 12.7256,
            'longitude' => 121.4389,
            'description' => 'Eastern valley quarry site',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        Quarry::create([
            'name' => 'North Ridge Quarry',
            'latitude' => 12.7512,
            'longitude' => 121.4123,
            'description' => 'Northern ridge quarry site',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        // Create an inactive quarry for testing
        Quarry::create([
            'name' => 'Old South Quarry',
            'latitude' => 12.6987,
            'longitude' => 121.4067,
            'description' => 'Decommissioned quarry site',
            'is_active' => false,
            'created_by' => $admin->id,
        ]);
    }
}
