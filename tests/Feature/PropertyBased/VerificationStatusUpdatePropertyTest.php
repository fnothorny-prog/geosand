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
 * Feature: geosand-quarry-system, Property 8: Verification status update
 * 
 * For any extraction that is verified by a Checkpoint user, the status should 
 * change from "pending" to "verified" and the verifier ID and timestamp should 
 * be recorded.
 * 
 * Validates: Requirements 3.3
 */
class VerificationStatusUpdatePropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that verification updates status and records verifier information.
     *
     * @return void
     */
    public function test_verification_updates_status_and_records_verifier()
    {
        $this->forAll(
            Generator\float(1, 1000),
            Generator\string()
        )
        ->then(function ($quantity, $notes) {
            // Create checkpoint user
            $checkpointUser = User::factory()->create(['role' => 'checkpoint']);
            
            // Create a pending extraction
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create();
            $extraction = Extraction::factory()->create([
                'operator_id' => $operator->id,
                'quarry_id' => $quarry->id,
                'quantity' => $quantity,
                'notes' => $notes,
                'status' => 'pending',
                'verified_by' => null,
                'verified_at' => null,
            ]);

            // Verify the extraction
            $response = $this->actingAs($checkpointUser, 'sanctum')
                ->postJson("/api/extractions/{$extraction->id}/verify");

            // Property: Status should change to "verified"
            $response->assertStatus(200);
            $extraction->refresh();
            
            $this->assertEquals('verified', $extraction->status);
            
            // Property: Verifier ID should be recorded
            $this->assertEquals($checkpointUser->id, $extraction->verified_by);
            
            // Property: Verification timestamp should be recorded
            $this->assertNotNull($extraction->verified_at);
        });
    }

    /**
     * Test that multiple extractions can be verified independently.
     *
     * @return void
     */
    public function test_multiple_extractions_verified_independently()
    {
        $this->forAll(
            Generator\int(2, 10)
        )
        ->then(function ($count) {
            // Create checkpoint user
            $checkpointUser = User::factory()->create(['role' => 'checkpoint']);
            
            $extractions = [];
            
            // Create multiple pending extractions
            for ($i = 0; $i < $count; $i++) {
                $operator = User::factory()->create(['role' => 'operator']);
                $quarry = Quarry::factory()->create();
                $extractions[] = Extraction::factory()->create([
                    'operator_id' => $operator->id,
                    'quarry_id' => $quarry->id,
                    'status' => 'pending',
                ]);
            }

            // Verify all extractions
            foreach ($extractions as $extraction) {
                $response = $this->actingAs($checkpointUser, 'sanctum')
                    ->postJson("/api/extractions/{$extraction->id}/verify");

                $response->assertStatus(200);
            }

            // Property: All extractions should be verified with correct data
            foreach ($extractions as $extraction) {
                $extraction->refresh();
                $this->assertEquals('verified', $extraction->status);
                $this->assertEquals($checkpointUser->id, $extraction->verified_by);
                $this->assertNotNull($extraction->verified_at);
            }
        });
    }

    /**
     * Test that non-pending extractions cannot be verified.
     *
     * @return void
     */
    public function test_non_pending_extractions_cannot_be_verified()
    {
        $this->forAll(
            Generator\elements(['verified', 'rejected'])
        )
        ->then(function ($status) {
            // Create checkpoint user
            $checkpointUser = User::factory()->create(['role' => 'checkpoint']);
            
            // Create an extraction with non-pending status
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create();
            $extraction = Extraction::factory()->create([
                'operator_id' => $operator->id,
                'quarry_id' => $quarry->id,
                'status' => $status,
            ]);

            $originalStatus = $extraction->status;
            $originalVerifiedBy = $extraction->verified_by;

            // Attempt to verify
            $response = $this->actingAs($checkpointUser, 'sanctum')
                ->postJson("/api/extractions/{$extraction->id}/verify");

            // Property: Should be rejected
            $response->assertStatus(422);
            
            // Property: Status should remain unchanged
            $extraction->refresh();
            $this->assertEquals($originalStatus, $extraction->status);
            $this->assertEquals($originalVerifiedBy, $extraction->verified_by);
        });
    }

    /**
     * Test that verification preserves original extraction data.
     *
     * @return void
     */
    public function test_verification_preserves_original_extraction_data()
    {
        $this->forAll(
            Generator\float(1, 1000),
            Generator\string()
        )
        ->then(function ($quantity, $notes) {
            // Create checkpoint user
            $checkpointUser = User::factory()->create(['role' => 'checkpoint']);
            
            // Create a pending extraction
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create();
            $extraction = Extraction::factory()->create([
                'operator_id' => $operator->id,
                'quarry_id' => $quarry->id,
                'quantity' => $quantity,
                'notes' => $notes,
                'status' => 'pending',
            ]);

            $originalQuantity = $extraction->quantity;
            $originalNotes = $extraction->notes;
            $originalOperatorId = $extraction->operator_id;
            $originalQuarryId = $extraction->quarry_id;

            // Verify the extraction
            $this->actingAs($checkpointUser, 'sanctum')
                ->postJson("/api/extractions/{$extraction->id}/verify");

            // Property: Original data should be preserved
            $extraction->refresh();
            $this->assertEquals($originalQuantity, $extraction->quantity);
            $this->assertEquals($originalNotes, $extraction->notes);
            $this->assertEquals($originalOperatorId, $extraction->operator_id);
            $this->assertEquals($originalQuarryId, $extraction->quarry_id);
        });
    }
}
