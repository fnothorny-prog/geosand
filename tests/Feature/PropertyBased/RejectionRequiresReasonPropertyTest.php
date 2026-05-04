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
 * Feature: geosand-quarry-system, Property 9: Rejection requires reason
 * 
 * For any extraction rejection by a Checkpoint user, the system should require 
 * a rejection_reason field and reject the operation if it is empty.
 * 
 * Validates: Requirements 3.4
 */
class RejectionRequiresReasonPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that rejection without reason is rejected.
     *
     * @return void
     */
    public function test_rejection_without_reason_is_rejected()
    {
        $this->forAll(
            Generator\int(1, 5)
        )
        ->then(function ($count) {
            // Create checkpoint user
            $checkpointUser = User::factory()->create(['role' => 'checkpoint']);
            
            for ($i = 0; $i < $count; $i++) {
                // Create a pending extraction
                $operator = User::factory()->create(['role' => 'operator']);
                $quarry = Quarry::factory()->create();
                $extraction = Extraction::factory()->create([
                    'operator_id' => $operator->id,
                    'quarry_id' => $quarry->id,
                    'status' => 'pending',
                ]);

                // Attempt to reject without reason
                $response = $this->actingAs($checkpointUser, 'sanctum')
                    ->postJson("/api/extractions/{$extraction->id}/reject", []);

                // Property: Should be rejected with validation error
                $response->assertStatus(422);
                $response->assertJsonValidationErrors(['rejection_reason']);

                // Verify extraction status remains unchanged
                $extraction->refresh();
                $this->assertEquals('pending', $extraction->status);
                $this->assertNull($extraction->rejection_reason);
            }
        });
    }

    /**
     * Test that rejection with empty string reason is rejected.
     *
     * @return void
     */
    public function test_rejection_with_empty_string_reason_is_rejected()
    {
        $this->forAll(
            Generator\elements(['', ' ', '  ', "\t", "\n"])
        )
        ->then(function ($emptyReason) {
            // Create checkpoint user
            $checkpointUser = User::factory()->create(['role' => 'checkpoint']);
            
            // Create a pending extraction
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create();
            $extraction = Extraction::factory()->create([
                'operator_id' => $operator->id,
                'quarry_id' => $quarry->id,
                'status' => 'pending',
            ]);

            // Attempt to reject with empty/whitespace reason
            $response = $this->actingAs($checkpointUser, 'sanctum')
                ->postJson("/api/extractions/{$extraction->id}/reject", [
                    'rejection_reason' => $emptyReason,
                ]);

            // Property: Should be rejected with validation error
            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['rejection_reason']);

            // Verify extraction status remains unchanged
            $extraction->refresh();
            $this->assertEquals('pending', $extraction->status);
            $this->assertNull($extraction->rejection_reason);
        });
    }

    /**
     * Test that rejection with valid reason succeeds.
     *
     * @return void
     */
    public function test_rejection_with_valid_reason_succeeds()
    {
        $this->forAll(
            Generator\string()
        )
        ->then(function ($reason) {
            // Skip empty reasons
            if (empty(trim($reason))) {
                return;
            }

            // Create checkpoint user
            $checkpointUser = User::factory()->create(['role' => 'checkpoint']);
            
            // Create a pending extraction
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create();
            $extraction = Extraction::factory()->create([
                'operator_id' => $operator->id,
                'quarry_id' => $quarry->id,
                'status' => 'pending',
            ]);

            // Reject with valid reason
            $response = $this->actingAs($checkpointUser, 'sanctum')
                ->postJson("/api/extractions/{$extraction->id}/reject", [
                    'rejection_reason' => $reason,
                ]);

            // Property: Should succeed
            $response->assertStatus(200);

            // Property: Status should be "rejected" and reason should be stored
            $extraction->refresh();
            $this->assertEquals('rejected', $extraction->status);
            $this->assertEquals($reason, $extraction->rejection_reason);
            $this->assertEquals($checkpointUser->id, $extraction->verified_by);
            $this->assertNotNull($extraction->verified_at);
        });
    }

    /**
     * Test that rejection records verifier information.
     *
     * @return void
     */
    public function test_rejection_records_verifier_information()
    {
        $this->forAll(
            Generator\string()
        )
        ->then(function ($reason) {
            // Skip empty reasons
            if (empty(trim($reason))) {
                return;
            }

            // Create checkpoint user
            $checkpointUser = User::factory()->create(['role' => 'checkpoint']);
            
            // Create a pending extraction
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create();
            $extraction = Extraction::factory()->create([
                'operator_id' => $operator->id,
                'quarry_id' => $quarry->id,
                'status' => 'pending',
                'verified_by' => null,
                'verified_at' => null,
            ]);

            // Reject the extraction
            $response = $this->actingAs($checkpointUser, 'sanctum')
                ->postJson("/api/extractions/{$extraction->id}/reject", [
                    'rejection_reason' => $reason,
                ]);

            // Property: Verifier information should be recorded
            $response->assertStatus(200);
            $extraction->refresh();
            
            $this->assertEquals($checkpointUser->id, $extraction->verified_by);
            $this->assertNotNull($extraction->verified_at);
        });
    }

    /**
     * Test that non-pending extractions cannot be rejected.
     *
     * @return void
     */
    public function test_non_pending_extractions_cannot_be_rejected()
    {
        $this->forAll(
            Generator\elements(['verified', 'rejected']),
            Generator\string()
        )
        ->then(function ($status, $reason) {
            // Skip empty reasons
            if (empty(trim($reason))) {
                return;
            }

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
            $originalReason = $extraction->rejection_reason;

            // Attempt to reject
            $response = $this->actingAs($checkpointUser, 'sanctum')
                ->postJson("/api/extractions/{$extraction->id}/reject", [
                    'rejection_reason' => $reason,
                ]);

            // Property: Should be rejected
            $response->assertStatus(422);
            
            // Property: Status and reason should remain unchanged
            $extraction->refresh();
            $this->assertEquals($originalStatus, $extraction->status);
            $this->assertEquals($originalReason, $extraction->rejection_reason);
        });
    }

    /**
     * Test that rejection preserves original extraction data.
     *
     * @return void
     */
    public function test_rejection_preserves_original_extraction_data()
    {
        $this->forAll(
            Generator\float(1, 1000),
            Generator\string(),
            Generator\string()
        )
        ->then(function ($quantity, $notes, $reason) {
            // Skip empty reasons
            if (empty(trim($reason))) {
                return;
            }

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

            // Reject the extraction
            $this->actingAs($checkpointUser, 'sanctum')
                ->postJson("/api/extractions/{$extraction->id}/reject", [
                    'rejection_reason' => $reason,
                ]);

            // Property: Original data should be preserved
            $extraction->refresh();
            $this->assertEquals($originalQuantity, $extraction->quantity);
            $this->assertEquals($originalNotes, $extraction->notes);
            $this->assertEquals($originalOperatorId, $extraction->operator_id);
            $this->assertEquals($originalQuarryId, $extraction->quarry_id);
        });
    }
}
