<?php

namespace Tests\Feature\PropertyBased;

use App\Models\Quarry;
use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: geosand-quarry-system, Property 13: Quarry deactivation prevents new extractions
 * 
 * For any extraction submission referencing a quarry with is_active=false, 
 * the system should reject the submission with an appropriate error message.
 * 
 * Validates: Requirements 5.4
 */
class QuarryDeactivationPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that extractions can be submitted to active quarries.
     *
     * @return void
     */
    public function test_active_quarries_accept_extractions()
    {
        $this->forAll(
            Generator\choose(1, 1000), // quantity
            Generator\elements(['cubic meters', 'tons', 'truckloads'])
        )
        ->then(function ($quantity, $unit) {
            // Create operator user
            $operator = User::factory()->create(['role' => 'operator']);

            // Create active quarry
            $quarry = Quarry::factory()->create([
                'is_active' => true,
            ]);

            // Attempt to submit extraction
            $response = $this->actingAs($operator, 'sanctum')->postJson('/api/extractions', [
                'quarry_id' => $quarry->id,
                'extraction_date' => now()->format('Y-m-d'),
                'extraction_time' => now()->format('H:i:s'),
                'quantity' => $quantity,
                'unit' => $unit,
                'notes' => 'Test extraction',
            ]);

            // Property: Active quarries should accept extraction submissions
            $response->assertStatus(201);
            $this->assertTrue($response->json('success'));
            $this->assertEquals('pending', $response->json('data.extraction.status'));
        });
    }

    /**
     * Test that extractions are rejected for inactive quarries.
     *
     * @return void
     */
    public function test_inactive_quarries_reject_extractions()
    {
        $this->forAll(
            Generator\choose(1, 1000), // quantity
            Generator\elements(['cubic meters', 'tons', 'truckloads'])
        )
        ->then(function ($quantity, $unit) {
            // Create operator user
            $operator = User::factory()->create(['role' => 'operator']);

            // Create inactive quarry
            $quarry = Quarry::factory()->create([
                'is_active' => false,
            ]);

            // Attempt to submit extraction
            $response = $this->actingAs($operator, 'sanctum')->postJson('/api/extractions', [
                'quarry_id' => $quarry->id,
                'extraction_date' => now()->format('Y-m-d'),
                'extraction_time' => now()->format('H:i:s'),
                'quantity' => $quantity,
                'unit' => $unit,
                'notes' => 'Test extraction',
            ]);

            // Property: Inactive quarries should reject extraction submissions
            $response->assertStatus(422);
            $this->assertFalse($response->json('success'));
            
            // Should have validation error for quarry_id
            $errors = $response->json('errors');
            $this->assertArrayHasKey('quarry_id', $errors);
            $this->assertStringContainsString('not active', $errors['quarry_id'][0]);
        });
    }

    /**
     * Test that deactivating a quarry prevents new extractions.
     *
     * @return void
     */
    public function test_deactivation_prevents_new_extractions()
    {
        $this->forAll(
            Generator\choose(1, 1000) // quantity
        )
        ->then(function ($quantity) {
            // Create admin and operator users
            $admin = User::factory()->create(['role' => 'admin']);
            $operator = User::factory()->create(['role' => 'operator']);

            // Create active quarry
            $quarry = Quarry::factory()->create([
                'is_active' => true,
                'created_by' => $admin->id,
            ]);

            // First extraction should succeed
            $response1 = $this->actingAs($operator, 'sanctum')->postJson('/api/extractions', [
                'quarry_id' => $quarry->id,
                'extraction_date' => now()->format('Y-m-d'),
                'extraction_time' => now()->format('H:i:s'),
                'quantity' => $quantity,
                'unit' => 'cubic meters',
            ]);
            $response1->assertStatus(201);

            // Deactivate the quarry
            $this->actingAs($admin, 'sanctum')->deleteJson("/api/quarries/{$quarry->id}");

            // Refresh quarry from database
            $quarry->refresh();
            $this->assertFalse($quarry->is_active);

            // Second extraction should fail
            $response2 = $this->actingAs($operator, 'sanctum')->postJson('/api/extractions', [
                'quarry_id' => $quarry->id,
                'extraction_date' => now()->format('Y-m-d'),
                'extraction_time' => now()->format('H:i:s'),
                'quantity' => $quantity,
                'unit' => 'cubic meters',
            ]);

            // Property: After deactivation, new extractions should be rejected
            $response2->assertStatus(422);
            $this->assertFalse($response2->json('success'));
        });
    }

    /**
     * Test that historical extractions are preserved when quarry is deactivated.
     *
     * @return void
     */
    public function test_deactivation_preserves_historical_extractions()
    {
        $this->forAll(
            Generator\choose(1, 10) // number of extractions
        )
        ->then(function ($extractionCount) {
            // Create admin and operator users
            $admin = User::factory()->create(['role' => 'admin']);
            $operator = User::factory()->create(['role' => 'operator']);

            // Create active quarry
            $quarry = Quarry::factory()->create([
                'is_active' => true,
                'created_by' => $admin->id,
            ]);

            // Create multiple extractions
            $extractionIds = [];
            for ($i = 0; $i < $extractionCount; $i++) {
                $extraction = \App\Models\Extraction::factory()->create([
                    'quarry_id' => $quarry->id,
                    'operator_id' => $operator->id,
                ]);
                $extractionIds[] = $extraction->id;
            }

            // Deactivate the quarry
            $this->actingAs($admin, 'sanctum')->deleteJson("/api/quarries/{$quarry->id}");

            // Property: All historical extractions should still exist
            foreach ($extractionIds as $extractionId) {
                $extraction = \App\Models\Extraction::find($extractionId);
                $this->assertNotNull($extraction, "Extraction {$extractionId} should still exist");
                $this->assertEquals($quarry->id, $extraction->quarry_id);
            }

            // Verify extraction count
            $this->assertEquals($extractionCount, $quarry->extractions()->count());
        });
    }
}
