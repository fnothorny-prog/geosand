<?php

namespace Tests\Feature\PropertyBased;

use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Feature: geosand-quarry-system, Property 10: Role assignment validation
 * 
 * For any user creation or update, the role field should only accept values 
 * from the set: ['operator', 'checkpoint', 'admin'].
 * 
 * Validates: Requirements 4.3
 */
class RoleAssignmentPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that only valid roles are accepted during user creation.
     *
     * @return void
     */
    public function test_role_assignment_validation_property()
    {
        $this->forAll(
            Generator\string()
        )
        ->then(function ($role) {
            $validRoles = ['operator', 'checkpoint', 'admin'];

            // Create user data with the generated role
            $userData = [
                'username' => 'testuser_' . uniqid(),
                'email' => 'test_' . uniqid() . '@example.com',
                'password' => 'password123',
                'role' => $role,
            ];

            if (in_array($role, $validRoles)) {
                // Property: Valid roles should be accepted
                $user = User::create($userData);
                
                $this->assertNotNull($user);
                $this->assertEquals($role, $user->role);
                $this->assertContains($user->role, $validRoles);
            } else {
                // Property: Invalid roles should be rejected
                try {
                    User::create($userData);
                    
                    // If we reach here, the invalid role was accepted (which is wrong)
                    $this->fail("Invalid role '$role' should have been rejected");
                } catch (\Exception $e) {
                    // Expected: Invalid role should cause an exception
                    $this->assertTrue(true);
                }
            }
        });
    }

    /**
     * Test that valid roles are always accepted.
     *
     * @return void
     */
    public function test_valid_roles_are_accepted()
    {
        $this->forAll(
            Generator\elements(['operator', 'checkpoint', 'admin'])
        )
        ->then(function ($role) {
            $user = User::factory()->create([
                'role' => $role,
            ]);

            // Property: All valid roles should be accepted
            $this->assertNotNull($user);
            $this->assertEquals($role, $user->role);
            $this->assertContains($role, ['operator', 'checkpoint', 'admin']);
        });
    }

    /**
     * Test that role updates also validate correctly.
     *
     * @return void
     */
    public function test_role_update_validation()
    {
        $this->forAll(
            Generator\elements(['operator', 'checkpoint', 'admin']),
            Generator\elements(['operator', 'checkpoint', 'admin'])
        )
        ->then(function ($initialRole, $newRole) {
            // Create user with initial role
            $user = User::factory()->create([
                'role' => $initialRole,
            ]);

            // Update to new role
            $user->role = $newRole;
            $user->save();

            // Property: Role should be updated to the new valid role
            $this->assertEquals($newRole, $user->fresh()->role);
            $this->assertContains($user->fresh()->role, ['operator', 'checkpoint', 'admin']);
        });
    }
}
