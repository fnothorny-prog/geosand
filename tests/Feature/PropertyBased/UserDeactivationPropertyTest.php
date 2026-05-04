<?php

namespace Tests\Feature\PropertyBased;

use App\Models\Extraction;
use App\Models\Quarry;
use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: geosand-quarry-system, Property 11: User deactivation preserves data
 * 
 * For any user deactivation operation, all historical extraction and verification 
 * records associated with that user should remain unchanged in the database.
 * 
 * Validates: Requirements 4.5
 */
class UserDeactivationPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that user deactivation preserves all associated extraction records.
     *
     * @return void
     */
    public function test_user_deactivation_preserves_extraction_data()
    {
        $this->forAll(
            Generator\choose(1, 10) // Number of extractions to create
        )
        ->then(function ($extractionCount) {
            // Create an operator user
            $operator = User::factory()->create([
                'role' => 'operator',
                'is_active' => true,
            ]);

            // Create a quarry
            $quarry = Quarry::factory()->create();

            // Create extractions for this operator
            $extractions = [];
            for ($i = 0; $i < $extractionCount; $i++) {
                $extractions[] = Extraction::factory()->create([
                    'operator_id' => $operator->id,
                    'quarry_id' => $quarry->id,
                ]);
            }

            // Store original extraction data
            $originalExtractionIds = array_map(fn($e) => $e->id, $extractions);
            $originalExtractionData = array_map(function($e) {
                return [
                    'id' => $e->id,
                    'operator_id' => $e->operator_id,
                    'quarry_id' => $e->quarry_id,
                    'quantity' => $e->quantity,
                    'extraction_date' => $e->extraction_date,
                    'status' => $e->status,
                ];
            }, $extractions);

            // Deactivate the user
            $operator->is_active = false;
            $operator->save();

            // Property: All extraction records should still exist
            $this->assertEquals($extractionCount, Extraction::where('operator_id', $operator->id)->count());

            // Property: All extraction data should remain unchanged
            foreach ($originalExtractionData as $originalData) {
                $extraction = Extraction::find($originalData['id']);
                
                $this->assertNotNull($extraction, "Extraction {$originalData['id']} should still exist");
                $this->assertEquals($originalData['operator_id'], $extraction->operator_id);
                $this->assertEquals($originalData['quarry_id'], $extraction->quarry_id);
                $this->assertEquals($originalData['quantity'], $extraction->quantity);
                $this->assertEquals($originalData['extraction_date'], $extraction->extraction_date);
                $this->assertEquals($originalData['status'], $extraction->status);
            }

            // Property: User should be deactivated
            $this->assertFalse($operator->fresh()->is_active);
        });
    }

    /**
     * Test that user deactivation preserves all verification records.
     *
     * @return void
     */
    public function test_user_deactivation_preserves_verification_data()
    {
        $this->forAll(
            Generator\choose(1, 10) // Number of verifications to create
        )
        ->then(function ($verificationCount) {
            // Create a checkpoint user
            $checkpoint = User::factory()->create([
                'role' => 'checkpoint',
                'is_active' => true,
            ]);

            // Create an operator and quarry
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create();

            // Create verified extractions
            $verifications = [];
            for ($i = 0; $i < $verificationCount; $i++) {
                $verifications[] = Extraction::factory()->create([
                    'operator_id' => $operator->id,
                    'quarry_id' => $quarry->id,
                    'status' => 'verified',
                    'verified_by' => $checkpoint->id,
                    'verified_at' => now(),
                ]);
            }

            // Store original verification data
            $originalVerificationData = array_map(function($v) {
                return [
                    'id' => $v->id,
                    'verified_by' => $v->verified_by,
                    'verified_at' => $v->verified_at,
                    'status' => $v->status,
                ];
            }, $verifications);

            // Deactivate the checkpoint user
            $checkpoint->is_active = false;
            $checkpoint->save();

            // Property: All verification records should still exist
            $this->assertEquals($verificationCount, Extraction::where('verified_by', $checkpoint->id)->count());

            // Property: All verification data should remain unchanged
            foreach ($originalVerificationData as $originalData) {
                $extraction = Extraction::find($originalData['id']);
                
                $this->assertNotNull($extraction, "Extraction {$originalData['id']} should still exist");
                $this->assertEquals($originalData['verified_by'], $extraction->verified_by);
                $this->assertEquals($originalData['verified_at']->toDateTimeString(), $extraction->verified_at->toDateTimeString());
                $this->assertEquals($originalData['status'], $extraction->status);
            }

            // Property: User should be deactivated
            $this->assertFalse($checkpoint->fresh()->is_active);
        });
    }

    /**
     * Test that deactivation via controller endpoint preserves data.
     *
     * @return void
     */
    public function test_controller_deactivation_preserves_data()
    {
        $this->forAll(
            Generator\choose(0, 5) // Number of extractions
        )
        ->then(function ($extractionCount) {
            // Create admin user for authentication
            $admin = User::factory()->create(['role' => 'admin']);

            // Create operator with extractions
            $operator = User::factory()->create([
                'role' => 'operator',
                'is_active' => true,
            ]);

            $quarry = Quarry::factory()->create();
            
            $extractionIds = [];
            for ($i = 0; $i < $extractionCount; $i++) {
                $extraction = Extraction::factory()->create([
                    'operator_id' => $operator->id,
                    'quarry_id' => $quarry->id,
                ]);
                $extractionIds[] = $extraction->id;
            }

            // Deactivate via API endpoint
            $response = $this->actingAs($admin, 'sanctum')
                ->deleteJson("/api/users/{$operator->id}");

            $response->assertStatus(200);

            // Property: All extractions should still exist
            foreach ($extractionIds as $extractionId) {
                $this->assertDatabaseHas('extractions', [
                    'id' => $extractionId,
                    'operator_id' => $operator->id,
                ]);
            }

            // Property: User should be deactivated
            $this->assertDatabaseHas('users', [
                'id' => $operator->id,
                'is_active' => false,
            ]);
        });
    }
}
