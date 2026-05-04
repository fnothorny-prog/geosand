<?php

namespace Tests\Feature\PropertyBased;

use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/**
 * Feature: geosand-quarry-system, Property 16: Session token expiration
 * 
 * For any API request with an expired session token, the system should 
 * reject the request with a 401 unauthorized error.
 * 
 * Validates: Requirements 7.3
 */
class SessionTokenExpirationPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that expired tokens are rejected.
     *
     * @return void
     */
    public function test_session_token_expiration_property()
    {
        $this->forAll(
            Generator\choose(1, 100) // Hours in the past
        )
        ->then(function ($hoursInPast) {
            // Create a user
            $user = User::factory()->create([
                'role' => 'operator',
                'is_active' => true,
            ]);

            // Create a token that expired in the past
            $expiresAt = now()->subHours($hoursInPast);
            $token = $user->createToken('test-token', ['*'], $expiresAt);

            // Property: Using an expired token should result in 401 error
            $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
                ->getJson('/api/user');

            $response->assertStatus(401);
            $response->assertJson([
                'success' => false,
                'message' => 'Unauthenticated',
            ]);
        });
    }

    /**
     * Test that valid (non-expired) tokens are accepted.
     *
     * @return void
     */
    public function test_valid_token_acceptance_property()
    {
        $this->forAll(
            Generator\choose(1, 100) // Hours in the future
        )
        ->then(function ($hoursInFuture) {
            // Create a user
            $user = User::factory()->create([
                'role' => 'operator',
                'is_active' => true,
            ]);

            // Create a token that expires in the future
            $expiresAt = now()->addHours($hoursInFuture);
            $token = $user->createToken('test-token', ['*'], $expiresAt);

            // Property: Using a valid token should succeed
            $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
                ->getJson('/api/user');

            $response->assertStatus(200);
            $response->assertJson([
                'success' => true,
            ]);
        });
    }
}
