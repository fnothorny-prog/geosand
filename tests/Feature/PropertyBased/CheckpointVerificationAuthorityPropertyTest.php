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
 * Feature: geosand-quarry-system, Property 7: Checkpoint verification authority
 * 
 * For any verification request from a user without the Checkpoint role, 
 * the system should reject the request with a 403 forbidden error.
 * 
 * Validates: Requirements 3.1
 */
class CheckpointVerificationAuthorityPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that only checkpoint users can verify extractions.
     *
     * @return void
     */
    public function test_non_checkpoint_users_cannot_verify_extractions()
    {
        $this->forAll(
            Generator\elements(['operator', 'admin'])
        )
        ->then(function ($role) {
            // Create a user with non-checkpoint role
            $user = User::factory()->create(['role' => $role]);
            
            // Create a pending extraction
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create();
            $extraction = Extraction::factory()->create([
                'operator_id' => $operator->id,
                'quarry_id' => $quarry->id,
                'status' => 'pending',
            ]);

            // Attempt to verify as non-checkpoint user
            $response = $this->actingAs($user, 'sanctum')
                ->postJson("/api/extractions/{$extraction->id}/verify");

            // Property: Should be rejected with 403 forbidden
            $response->assertStatus(403);
            $response->assertJson([
                'success' => false,
                'message' => 'Unauthorized action',
            ]);

            // Verify extraction status remains unchanged
            $extraction->refresh();
            $this->assertEquals('pending', $extraction->status);
            $this->assertNull($extraction->verified_by);
        });
    }

    /**
     * Test that only checkpoint users can reject extractions.
     *
     * @return void
     */
    public function test_non_checkpoint_users_cannot_reject_extractions()
    {
        $this->forAll(
            Generator\elements(['operator', 'admin']),
            Generator\string()
        )
        ->then(function ($role, $reason) {
            // Skip empty reasons as they would fail validation anyway
            if (empty(trim($reason))) {
                return;
            }

            // Create a user with non-checkpoint role
            $user = User::factory()->create(['role' => $role]);
            
            // Create a pending extraction
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create();
            $extraction = Extraction::factory()->create([
                'operator_id' => $operator->id,
                'quarry_id' => $quarry->id,
                'status' => 'pending',
            ]);

            // Attempt to reject as non-checkpoint user
            $response = $this->actingAs($user, 'sanctum')
                ->postJson("/api/extractions/{$extraction->id}/reject", [
                    'rejection_reason' => $reason,
                ]);

            // Property: Should be rejected with 403 forbidden
            $response->assertStatus(403);
            $response->assertJson([
                'success' => false,
                'message' => 'Unauthorized action',
            ]);

            // Verify extraction status remains unchanged
            $extraction->refresh();
            $this->assertEquals('pending', $extraction->status);
            $this->assertNull($extraction->verified_by);
            $this->assertNull($extraction->rejection_reason);
        });
    }

    /**
     * Test that checkpoint users can successfully verify extractions.
     *
     * @return void
     */
    public function test_checkpoint_users_can_verify_extractions()
    {
        $this->forAll(
            Generator\int(1, 10)
        )
        ->then(function ($count) {
            // Create a checkpoint user
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

                // Verify as checkpoint user
                $response = $this->actingAs($checkpointUser, 'sanctum')
                    ->postJson("/api/extractions/{$extraction->id}/verify");

                // Property: Checkpoint users should be able to verify
                $response->assertStatus(200);
                $response->assertJson([
                    'success' => true,
                ]);
            }
        });
    }

    /**
     * Test that unauthenticated users cannot verify extractions.
     *
     * @return void
     */
    public function test_unauthenticated_users_cannot_verify_extractions()
    {
        $this->forAll(
            Generator\int(1, 5)
        )
        ->then(function ($extractionId) {
            // Create a pending extraction
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create();
            $extraction = Extraction::factory()->create([
                'operator_id' => $operator->id,
                'quarry_id' => $quarry->id,
                'status' => 'pending',
            ]);

            // Attempt to verify without authentication
            $response = $this->postJson("/api/extractions/{$extraction->id}/verify");

            // Property: Should be rejected with 401 unauthorized
            $response->assertStatus(401);

            // Verify extraction status remains unchanged
            $extraction->refresh();
            $this->assertEquals('pending', $extraction->status);
            $this->assertNull($extraction->verified_by);
        });
    }
}
