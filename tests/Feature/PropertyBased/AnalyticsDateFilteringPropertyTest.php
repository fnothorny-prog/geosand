<?php

namespace Tests\Feature\PropertyBased;

use App\Models\Extraction;
use App\Models\Quarry;
use App\Models\User;
use Carbon\Carbon;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: geosand-quarry-system, Property 14: Analytics date filtering
 * 
 * For any analytics request with a date range filter, all returned data points 
 * should have timestamps within the specified range (inclusive).
 * 
 * Validates: Requirements 6.4
 */
class AnalyticsDateFilteringPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that extraction volume analytics respects date range filters.
     *
     * @return void
     */
    public function test_extraction_volume_date_filtering_property()
    {
        $this->forAll(
            Generator\choose(10, 30), // end days ago
            Generator\choose(2, 15)   // range length
        )
        ->then(function ($endDaysAgo, $rangeLength) {
            // Ensure positive values (in case of shrinking)
            $endDaysAgo = max(1, abs($endDaysAgo));
            $rangeLength = max(2, abs($rangeLength));
            $startDaysAgo = $endDaysAgo + $rangeLength;
            
            // Create admin user
            $admin = User::factory()->create(['role' => 'admin']);
            $operator = User::factory()->create(['role' => 'operator']);
            $checkpoint = User::factory()->create(['role' => 'checkpoint']);
            $quarry = Quarry::factory()->create();

            // Define date range - start is further in the past, end is more recent
            $startDate = Carbon::now()->startOfDay()->subDays($startDaysAgo);
            $endDate = Carbon::now()->startOfDay()->subDays($endDaysAgo);

            // Create extractions before the range
            $extractionBefore = Extraction::factory()->create([
                'quarry_id' => $quarry->id,
                'operator_id' => $operator->id,
                'extraction_date' => $startDate->copy()->subDays(1),
                'status' => 'verified',
                'verified_by' => $checkpoint->id,
                'verified_at' => now(),
            ]);

            // Create extractions within the range
            $extractionsInRange = [];
            $rangeLength = $startDaysAgo - $endDaysAgo;
            $numInRange = min($rangeLength, 5);
            for ($i = 0; $i < $numInRange; $i++) {
                $daysFromStart = ($i * $rangeLength) / max($numInRange, 1);
                $extractionsInRange[] = Extraction::factory()->create([
                    'quarry_id' => $quarry->id,
                    'operator_id' => $operator->id,
                    'extraction_date' => $startDate->copy()->addDays($daysFromStart),
                    'status' => 'verified',
                    'verified_by' => $checkpoint->id,
                    'verified_at' => now(),
                ]);
            }

            // Create extractions after the range (only if endDate is not today)
            if ($endDaysAgo > 0) {
                $extractionAfter = Extraction::factory()->create([
                    'quarry_id' => $quarry->id,
                    'operator_id' => $operator->id,
                    'extraction_date' => $endDate->copy()->addDays(1),
                    'status' => 'verified',
                    'verified_by' => $checkpoint->id,
                    'verified_at' => now(),
                ]);
            }

            // Make API request with date range
            $response = $this->actingAs($admin, 'sanctum')
                ->getJson('/api/analytics/extractions?' . http_build_query([
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ]));

            $response->assertStatus(200);
            $data = $response->json('data.extraction_volume');

            // Property: All returned data points should be within the date range
            foreach ($data as $dataPoint) {
                $date = Carbon::parse($dataPoint['date']);
                $this->assertTrue(
                    $date->greaterThanOrEqualTo($startDate->startOfDay()) &&
                    $date->lessThanOrEqualTo($endDate->endOfDay()),
                    "Date {$dataPoint['date']} is not within range {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}"
                );
            }
        });
    }

    /**
     * Test that verification rates analytics respects date range filters.
     *
     * @return void
     */
    public function test_verification_rates_date_filtering_property()
    {
        $this->forAll(
            Generator\choose(10, 30), // end days ago
            Generator\choose(2, 15)   // range length
        )
        ->then(function ($endDaysAgo, $rangeLength) {
            // Ensure positive values (in case of shrinking)
            $endDaysAgo = max(1, abs($endDaysAgo));
            $rangeLength = max(2, abs($rangeLength));
            $startDaysAgo = $endDaysAgo + $rangeLength;
            
            // Ensure clean database state
            Extraction::query()->delete();
            User::query()->delete();
            Quarry::query()->delete();
            
            // Create users
            $admin = User::factory()->create(['role' => 'admin']);
            $operator = User::factory()->create(['role' => 'operator']);
            $checkpoint = User::factory()->create(['role' => 'checkpoint']);
            $quarry = Quarry::factory()->create();

            // Define date range - start is further in the past, end is more recent
            $startDate = Carbon::now()->startOfDay()->subDays($startDaysAgo);
            $endDate = Carbon::now()->startOfDay()->subDays($endDaysAgo);

            // Create extractions verified before the range
            Extraction::factory()->create([
                'quarry_id' => $quarry->id,
                'operator_id' => $operator->id,
                'extraction_date' => $startDate->copy()->subDays(2),
                'status' => 'verified',
                'verified_by' => $checkpoint->id,
                'verified_at' => $startDate->copy()->subDays(1),
            ]);

            // Create extractions verified within the range
            $rangeLength = $startDaysAgo - $endDaysAgo;
            $numInRange = min($rangeLength, 5);
            for ($i = 0; $i < $numInRange; $i++) {
                $daysFromStart = ($i * $rangeLength) / max($numInRange, 1);
                $verifiedDate = $startDate->copy()->addDays($daysFromStart);
                Extraction::factory()->create([
                    'quarry_id' => $quarry->id,
                    'operator_id' => $operator->id,
                    'extraction_date' => $verifiedDate,
                    'status' => 'verified',
                    'verified_by' => $checkpoint->id,
                    'verified_at' => $verifiedDate,
                ]);
            }

            // Create extractions verified after the range (only if endDate is not today)
            if ($endDaysAgo > 0) {
                Extraction::factory()->create([
                    'quarry_id' => $quarry->id,
                    'operator_id' => $operator->id,
                    'extraction_date' => $endDate->copy()->addDays(2),
                    'status' => 'verified',
                    'verified_by' => $checkpoint->id,
                    'verified_at' => $endDate->copy()->addDays(1),
                ]);
            }

            // Make API request with date range
            $response = $this->actingAs($admin, 'sanctum')
                ->getJson('/api/analytics/verifications?' . http_build_query([
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ]));

            $response->assertStatus(200);
            $data = $response->json('data.verification_rates');

            // Property: The count should only include verifications within the date range
            // We should have exactly min($rangeLength, 5) verifications
            if (!empty($data)) {
                $totalVerifications = array_sum(array_column($data, 'total_verifications'));
                $this->assertEquals(
                    $numInRange,
                    $totalVerifications,
                    "Total verifications should match the number created within the date range"
                );
            }
        });
    }

    /**
     * Test that extraction by quarry analytics respects date range filters.
     *
     * @return void
     */
    public function test_extraction_by_quarry_date_filtering_property()
    {
        $this->forAll(
            Generator\choose(10, 30), // end days ago
            Generator\choose(2, 15)   // range length
        )
        ->then(function ($endDaysAgo, $rangeLength) {
            // Ensure positive values (in case of shrinking)
            $endDaysAgo = max(1, abs($endDaysAgo));
            $rangeLength = max(2, abs($rangeLength));
            $startDaysAgo = $endDaysAgo + $rangeLength;
            
            // Create users
            $admin = User::factory()->create(['role' => 'admin']);
            $operator = User::factory()->create(['role' => 'operator']);
            $checkpoint = User::factory()->create(['role' => 'checkpoint']);
            $quarry = Quarry::factory()->create();

            // Define date range - start is further in the past, end is more recent
            $startDate = Carbon::now()->startOfDay()->subDays($startDaysAgo);
            $endDate = Carbon::now()->startOfDay()->subDays($endDaysAgo);

            // Create extractions before the range
            Extraction::factory()->create([
                'quarry_id' => $quarry->id,
                'operator_id' => $operator->id,
                'extraction_date' => $startDate->copy()->subDays(1),
                'status' => 'verified',
                'verified_by' => $checkpoint->id,
                'verified_at' => now(),
            ]);

            // Create extractions within the range
            $rangeLength = $startDaysAgo - $endDaysAgo;
            $expectedCount = min($rangeLength, 5);
            for ($i = 0; $i < $expectedCount; $i++) {
                $daysFromStart = ($i * $rangeLength) / max($expectedCount, 1);
                Extraction::factory()->create([
                    'quarry_id' => $quarry->id,
                    'operator_id' => $operator->id,
                    'extraction_date' => $startDate->copy()->addDays($daysFromStart),
                    'status' => 'verified',
                    'verified_by' => $checkpoint->id,
                    'verified_at' => now(),
                ]);
            }

            // Create extractions after the range (only if endDate is not today)
            if ($endDaysAgo > 0) {
                Extraction::factory()->create([
                    'quarry_id' => $quarry->id,
                    'operator_id' => $operator->id,
                    'extraction_date' => $endDate->copy()->addDays(1),
                    'status' => 'verified',
                    'verified_by' => $checkpoint->id,
                    'verified_at' => now(),
                ]);
            }

            // Make API request with date range
            $response = $this->actingAs($admin, 'sanctum')
                ->getJson('/api/analytics/quarries?' . http_build_query([
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ]));

            $response->assertStatus(200);
            $data = $response->json('data.extraction_by_quarry');

            // Property: The extraction count should only include extractions within the date range
            if (!empty($data)) {
                $quarryData = collect($data)->firstWhere('quarry_id', $quarry->id);
                if ($quarryData) {
                    $this->assertEquals(
                        $expectedCount,
                        $quarryData['extraction_count'],
                        "Extraction count should match the number created within the date range"
                    );
                }
            }
        });
    }

    /**
     * Test that analytics without date filters return all data.
     *
     * @return void
     */
    public function test_analytics_without_date_filter_returns_all_data()
    {
        $this->forAll(
            Generator\choose(2, 10) // number of extractions (use choose instead of int)
        )
        ->then(function ($count) {
            // Ensure clean database state
            Extraction::query()->delete();
            User::query()->delete();
            Quarry::query()->delete();
            
            // Create users
            $admin = User::factory()->create(['role' => 'admin']);
            $operator = User::factory()->create(['role' => 'operator']);
            $checkpoint = User::factory()->create(['role' => 'checkpoint']);
            $quarry = Quarry::factory()->create();

            // Create extractions across different dates
            for ($i = 0; $i < $count; $i++) {
                Extraction::factory()->create([
                    'quarry_id' => $quarry->id,
                    'operator_id' => $operator->id,
                    'extraction_date' => Carbon::now()->subDays($i),
                    'status' => 'verified',
                    'verified_by' => $checkpoint->id,
                    'verified_at' => now(),
                ]);
            }

            // Make API request without date range
            $response = $this->actingAs($admin, 'sanctum')
                ->getJson('/api/analytics/extractions');

            $response->assertStatus(200);
            $data = $response->json('data.extraction_volume');

            // Property: Without date filters, should return data for all extractions
            $totalCount = array_sum(array_column($data, 'extraction_count'));
            $this->assertEquals(
                $count,
                $totalCount,
                "Without date filters, should return all extractions. Expected: {$count}, Got: {$totalCount}"
            );
        });
    }
}
