<?php

namespace Tests\Feature\PropertyBased;

use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: geosand-quarry-system, Property 20: Input validation before processing
 * 
 * For any API request with a request body, the Laravel backend should validate 
 * all input fields before executing business logic.
 * 
 * Validates: Requirements 10.2
 */
class InputValidationPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that user creation validates input before processing.
     *
     * @return void
     */
    public function test_user_creation_validates_before_processing()
    {
        $this->forAll(
            Generator\string(),
            Generator\string(),
            Generator\string()
        )
        ->then(function ($username, $email, $password) {
            $admin = User::factory()->create(['role' => 'admin']);

            // Attempt to create user with potentially invalid data
            $response = $this->actingAs($admin, 'sanctum')
                ->postJson('/api/users', [
                    'username' => $username,
                    'email' => $email,
                    'password' => $password,
                    'role' => 'operator',
                ]);

            // Property: If validation fails, should return 422 status
            // Property: If validation fails, no user should be created
            if ($response->status() === 422) {
                // Validation failed - ensure no user was created with this data
                $this->assertDatabaseMissing('users', [
                    'username' => $username,
                    'email' => $email,
                ]);
                
                // Should have error messages
                $response->assertJsonStructure(['message', 'errors']);
            } else if ($response->status() === 201) {
                // Validation passed - user should be created
                $this->assertDatabaseHas('users', [
                    'username' => $username,
                    'email' => $email,
                ]);
            }
        });
    }

    /**
     * Test that user update validates input before processing.
     *
     * @return void
     */
    public function test_user_update_validates_before_processing()
    {
        $this->forAll(
            Generator\string()
        )
        ->then(function ($newEmail) {
            $admin = User::factory()->create(['role' => 'admin']);
            $user = User::factory()->create(['role' => 'operator']);

            // Store original data
            $originalEmail = $user->email;
            $originalUsername = $user->username;

            // Attempt to update with potentially invalid email
            $response = $this->actingAs($admin, 'sanctum')
                ->putJson("/api/users/{$user->id}", [
                    'email' => $newEmail,
                ]);

            // Property: If validation fails, user data should remain unchanged
            if ($response->status() === 422) {
                $user->refresh();
                $this->assertEquals($originalEmail, $user->email);
                $this->assertEquals($originalUsername, $user->username);
                
                // Should have error messages
                $response->assertJsonStructure(['message', 'errors']);
            } else if ($response->status() === 200) {
                // Validation passed - email should be updated
                $user->refresh();
                $this->assertEquals($newEmail, $user->email);
            }
        });
    }

    /**
     * Test that invalid role values are rejected before processing.
     *
     * @return void
     */
    public function test_invalid_role_rejected_before_processing()
    {
        $this->forAll(
            Generator\string()
        )
        ->then(function ($role) {
            $admin = User::factory()->create(['role' => 'admin']);
            $validRoles = ['operator', 'checkpoint', 'admin'];

            // Count users before request
            $userCountBefore = User::count();

            // Attempt to create user with potentially invalid role
            $response = $this->actingAs($admin, 'sanctum')
                ->postJson('/api/users', [
                    'username' => 'testuser_' . uniqid(),
                    'email' => 'test_' . uniqid() . '@example.com',
                    'password' => 'password123',
                    'role' => $role,
                ]);

            if (!in_array($role, $validRoles)) {
                // Property: Invalid role should be rejected with 422
                $this->assertEquals(422, $response->status());
                
                // Property: No user should be created
                $this->assertEquals($userCountBefore, User::count());
                
                // Property: Should have validation error for role field
                $response->assertJsonValidationErrors(['role']);
            } else {
                // Property: Valid role should be accepted
                $this->assertEquals(201, $response->status());
                $this->assertEquals($userCountBefore + 1, User::count());
            }
        });
    }

    /**
     * Test that missing required fields are caught by validation.
     *
     * @return void
     */
    public function test_missing_required_fields_rejected()
    {
        $this->forAll(
            Generator\elements([
                ['email' => 'test@example.com', 'password' => 'password123', 'role' => 'operator'], // missing username
                ['username' => 'testuser', 'password' => 'password123', 'role' => 'operator'], // missing email
                ['username' => 'testuser', 'email' => 'test@example.com', 'role' => 'operator'], // missing password
                ['username' => 'testuser', 'email' => 'test@example.com', 'password' => 'password123'], // missing role
            ])
        )
        ->then(function ($userData) {
            $admin = User::factory()->create(['role' => 'admin']);

            // Count users before request
            $userCountBefore = User::count();

            // Attempt to create user with missing required field
            $response = $this->actingAs($admin, 'sanctum')
                ->postJson('/api/users', $userData);

            // Property: Missing required fields should result in 422 validation error
            $this->assertEquals(422, $response->status());
            
            // Property: No user should be created
            $this->assertEquals($userCountBefore, User::count());
            
            // Property: Response should contain validation errors
            $response->assertJsonStructure(['message', 'errors']);
        });
    }

    /**
     * Test that validation occurs before any database operations.
     *
     * @return void
     */
    public function test_validation_before_database_operations()
    {
        $this->forAll(
            Generator\choose(1, 100)
        )
        ->then(function ($iteration) {
            $uniqueId = uniqid() . '_' . $iteration;
            
            $admin = User::factory()->create([
                'role' => 'admin',
                'username' => 'admin_' . $uniqueId,
                'email' => 'admin_' . $uniqueId . '@example.com',
            ]);

            // Create a user with valid data
            $existingUser = User::factory()->create([
                'username' => 'existing_user_' . $uniqueId,
                'email' => 'existing_' . $uniqueId . '@example.com',
            ]);

            // Count users before request
            $userCountBefore = User::count();

            // Attempt to create user with duplicate username (should fail validation)
            $response = $this->actingAs($admin, 'sanctum')
                ->postJson('/api/users', [
                    'username' => 'existing_user_' . $uniqueId, // Duplicate
                    'email' => 'new_' . $uniqueId . '@example.com',
                    'password' => 'password123',
                    'role' => 'operator',
                ]);

            // Property: Duplicate username should be rejected
            $this->assertEquals(422, $response->status());
            
            // Property: No new user should be created
            $this->assertEquals($userCountBefore, User::count());
            
            // Property: Should have validation error for username
            $response->assertJsonValidationErrors(['username']);
        });
    }
}
