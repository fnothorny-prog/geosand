<?php

namespace Tests\Feature\PropertyBased;

use App\Models\Quarry;
use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: geosand-quarry-system, Property 4: Operator authentication requirement
 * 
 * For any extraction submission request without a valid authentication token, 
 * the system should reject the request with a 401 unauthorized error.
 * 
 * Validates: Requirements 2.1
 */
class OperatorAuthenticationPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that extraction submissions without authentication are rejected.
     *
     * @return void
     */
    public function test_operator_authentication_requirement_property()
    {
        $this->forAll(
            Generator\choose(1, 1000), // quantity
            Generator\elements(['cubic meters', 'tons', 'truckloads']) // unit
        )
        ->then(function ($quantity, $unit) {
            // Create a quarry for the extraction to reference
            $quarry = Quarry::factory()->create();

            $extractionData = [
                'quarry_id' => $quarry->id,
                'extraction_date' => '2025-12-08',
                'extraction_time' => '10:00:00',
                'quantity' => $quantity,
                'unit' => $unit,
            ];

            // Property: Attempting to submit extraction without authentication should fail with 401
            $response = $this->postJson('/api/extractions', $extractionData);

            $response->assertStatus(401);
            $response->assertJson([
                'success' => false,
                'message' => 'Unauthenticated',
            ]);

            // Verify no extraction was created in the database
            $this->assertDatabaseMissing('extractions', [
                'quarry_id' => $extractionData['quarry_id'],
                'quantity' => $extractionData['quantity'],
            ]);
        });
    }

    /**
     * Test that extraction submissions with invalid tokens are rejected.
     *
     * @return void
     */
    public function test_invalid_token_rejection_property()
    {
        $this->forAll(
            Generator\string()
        )
        ->then(function ($invalidToken) {
            // Skip empty tokens as they're handled by the no-token case
            if (strlen($invalidToken) < 1) {
                return;
            }

            // Create a quarry for the extraction to reference
            $quarry = Quarry::factory()->create();

            $extractionData = [
                'quarry_id' => $quarry->id,
                'extraction_date' => '2025-12-08',
                'extraction_time' => '10:00:00',
                'quantity' => 100,
                'unit' => 'cubic meters',
            ];

            // Property: Attempting to submit with an invalid token should fail with 401
            $response = $this->withHeader('Authorization', 'Bearer ' . $invalidToken)
                ->postJson('/api/extractions', $extractionData);

            $response->assertStatus(401);
        });
    }
}
