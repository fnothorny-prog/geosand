<?php

namespace Database\Seeders;

use App\Models\Extraction;
use App\Models\Quarry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ExtractionSeeder extends Seeder
{
    /**
     * Seed sample extractions with various statuses for testing.
     */
    public function run(): void
    {
        // Get users and quarries
        $operators = User::where('role', 'operator')->where('is_active', true)->get();
        $checkpoints = User::where('role', 'checkpoint')->get();
        $quarries = Quarry::where('is_active', true)->get();

        if ($operators->isEmpty() || $quarries->isEmpty()) {
            $this->command->warn('No operators or quarries found. Please run user and quarry seeders first.');
            return;
        }

        // Create pending extractions
        Extraction::create([
            'quarry_id' => $quarries[0]->id,
            'operator_id' => $operators[0]->id,
            'extraction_date' => Carbon::today(),
            'extraction_time' => '08:30:00',
            'quantity' => 150.50,
            'unit' => 'cubic meters',
            'status' => 'pending',
            'notes' => 'Morning extraction from main site',
        ]);

        Extraction::create([
            'quarry_id' => $quarries[1]->id,
            'operator_id' => $operators[1]->id,
            'extraction_date' => Carbon::today(),
            'extraction_time' => '10:15:00',
            'quantity' => 200.75,
            'unit' => 'cubic meters',
            'status' => 'pending',
            'notes' => 'Regular extraction operation',
        ]);

        Extraction::create([
            'quarry_id' => $quarries[2]->id,
            'operator_id' => $operators[0]->id,
            'extraction_date' => Carbon::yesterday(),
            'extraction_time' => '14:00:00',
            'quantity' => 175.25,
            'unit' => 'cubic meters',
            'status' => 'pending',
            'notes' => null,
        ]);

        // Create verified extractions
        if (!$checkpoints->isEmpty()) {
            Extraction::create([
                'quarry_id' => $quarries[0]->id,
                'operator_id' => $operators[0]->id,
                'extraction_date' => Carbon::yesterday(),
                'extraction_time' => '09:00:00',
                'quantity' => 180.00,
                'unit' => 'cubic meters',
                'status' => 'verified',
                'notes' => 'Standard extraction',
                'verified_by' => $checkpoints[0]->id,
                'verified_at' => Carbon::yesterday()->addHours(3),
            ]);

            Extraction::create([
                'quarry_id' => $quarries[1]->id,
                'operator_id' => $operators[1]->id,
                'extraction_date' => Carbon::now()->subDays(2),
                'extraction_time' => '11:30:00',
                'quantity' => 220.50,
                'unit' => 'cubic meters',
                'status' => 'verified',
                'notes' => 'Large extraction operation',
                'verified_by' => $checkpoints[0]->id,
                'verified_at' => Carbon::now()->subDays(2)->addHours(4),
            ]);

            Extraction::create([
                'quarry_id' => $quarries[2]->id,
                'operator_id' => $operators[0]->id,
                'extraction_date' => Carbon::now()->subDays(3),
                'extraction_time' => '08:00:00',
                'quantity' => 165.75,
                'unit' => 'cubic meters',
                'status' => 'verified',
                'notes' => 'Early morning extraction',
                'verified_by' => $checkpoints[1]->id,
                'verified_at' => Carbon::now()->subDays(3)->addHours(2),
            ]);

            Extraction::create([
                'quarry_id' => $quarries[3]->id,
                'operator_id' => $operators[2]->id,
                'extraction_date' => Carbon::now()->subDays(4),
                'extraction_time' => '13:45:00',
                'quantity' => 195.00,
                'unit' => 'cubic meters',
                'status' => 'verified',
                'notes' => 'Afternoon extraction',
                'verified_by' => $checkpoints[0]->id,
                'verified_at' => Carbon::now()->subDays(4)->addHours(3),
            ]);

            // Create rejected extractions
            Extraction::create([
                'quarry_id' => $quarries[1]->id,
                'operator_id' => $operators[1]->id,
                'extraction_date' => Carbon::yesterday(),
                'extraction_time' => '15:30:00',
                'quantity' => 250.00,
                'unit' => 'cubic meters',
                'status' => 'rejected',
                'notes' => 'Large quantity extraction',
                'verified_by' => $checkpoints[0]->id,
                'verified_at' => Carbon::yesterday()->addHours(5),
                'rejection_reason' => 'Quantity exceeds daily limit for this quarry',
            ]);

            Extraction::create([
                'quarry_id' => $quarries[0]->id,
                'operator_id' => $operators[0]->id,
                'extraction_date' => Carbon::now()->subDays(2),
                'extraction_time' => '16:00:00',
                'quantity' => 120.00,
                'unit' => 'cubic meters',
                'status' => 'rejected',
                'notes' => 'Late afternoon extraction',
                'verified_by' => $checkpoints[1]->id,
                'verified_at' => Carbon::now()->subDays(2)->addHours(2),
                'rejection_reason' => 'Extraction time outside permitted hours',
            ]);

            Extraction::create([
                'quarry_id' => $quarries[2]->id,
                'operator_id' => $operators[2]->id,
                'extraction_date' => Carbon::now()->subDays(5),
                'extraction_time' => '10:00:00',
                'quantity' => 140.50,
                'unit' => 'cubic meters',
                'status' => 'rejected',
                'notes' => null,
                'verified_by' => $checkpoints[0]->id,
                'verified_at' => Carbon::now()->subDays(5)->addHours(1),
                'rejection_reason' => 'Missing required documentation',
            ]);
        }

        // Create some older extractions for analytics testing
        if (!$checkpoints->isEmpty()) {
            for ($i = 7; $i <= 30; $i++) {
                $status = fake()->randomElement(['verified', 'verified', 'verified', 'rejected']);
                $operator = $operators->random();
                $quarry = $quarries->random();
                $checkpoint = $checkpoints->random();
                
                $extraction = Extraction::create([
                    'quarry_id' => $quarry->id,
                    'operator_id' => $operator->id,
                    'extraction_date' => Carbon::now()->subDays($i),
                    'extraction_time' => fake()->time(),
                    'quantity' => fake()->randomFloat(2, 100, 250),
                    'unit' => 'cubic meters',
                    'status' => $status,
                    'notes' => fake()->optional()->sentence(),
                    'verified_by' => $checkpoint->id,
                    'verified_at' => Carbon::now()->subDays($i)->addHours(rand(1, 6)),
                ]);

                if ($status === 'rejected') {
                    $extraction->rejection_reason = fake()->randomElement([
                        'Quantity exceeds daily limit',
                        'Missing documentation',
                        'Extraction time outside permitted hours',
                        'Incorrect location data',
                    ]);
                    $extraction->save();
                }
            }
        }
    }
}
