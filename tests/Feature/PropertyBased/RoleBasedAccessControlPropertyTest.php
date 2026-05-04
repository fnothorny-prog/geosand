<?php

namespace Tests\Feature\PropertyBased;

use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: geosand-quarry-system, Property 17: Role-based access control
 * 
 * For any API endpoint with role restrictions, requests from users without 
 * the required role should be rejected with a 403 forbidden error.
 * 
 * Validates: Requirements 7.4
 */
class RoleBasedAccessControlPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that non-admin users cannot access admin-only endpoints.
     *
     * @return void
     */
    public function test_role_based_access_control_property()
    {
        $this->forAll(
            Generator\elements(['operator', 'checkpoint']) // Non-admin roles
        )
        ->then(function ($role) {
            // Create a user with a non-admin role
            $user = User::factory()->create([
                'role' => $role,
                'is_active' => true,
            ]);

            // Create a valid token for the user
            $token = $user->createToken('test-token', ['*'], now()->addHours(24))->plainTextToken;

            // Property: Non-admin users should not be able to register new users
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->postJson('/api/register', [
                    'username' => 'newuser',
                    'email' => 'newuser@example.com',
                    'password' => 'password123',
                    'role' => 'operator',
                ]);

            $response->assertStatus(403);
            $response->assertJson([
                'success' => false,
                'message' => 'Unauthorized action',
            ]);

            // Verify no user was created
            $this->assertDatabaseMissing('users', [
                'username' => 'newuser',
                'email' => 'newuser@example.com',
            ]);
        });
    }

    /**
     * Test that admin users can access admin-only endpoints.
     *
     * @return void
     */
    public function test_admin_access_allowed_property()
    {
        $this->forAll(
            Generator\associative([
                'username' => Generator\string(),
                'email' => Generator\string(),
                'password' => Generator\string(),
                'role' => Generator\elements(['operator', 'checkpoint', 'admin']),
            ])
        )
        ->then(function ($userData) {
            // Skip invalid data
            if (strlen($userData['username']) < 1 || strlen($userData['password']) < 8) {
                return;
            }

            // Ensure email has basic format
            if (!str_contains($userData['email'], '@')) {
                $userData['email'] = $userData['username'] . '@example.com';
            }

            // Create an admin user
            $admin = User::factory()->create([
                'role' => 'admin',
                'is_active' => true,
            ]);

            // Create a valid token for the admin
            $token = $admin->createToken('test-token', ['*'], now()->addHours(24))->plainTextToken;

            // Property: Admin users should be able to register new users
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->postJson('/api/register', $userData);

            // Should succeed (201) or fail with validation error (422), but not 403
            $this->assertNotEquals(403, $response->status(), 'Admin should not receive 403 Forbidden');
            
            if ($response->status() === 201) {
                // If successful, verify user was created
                $this->assertDatabaseHas('users', [
                    'username' => $userData['username'],
                    'role' => $userData['role'],
                ]);
            }
        });
    }
}
