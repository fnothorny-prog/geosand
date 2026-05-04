<?php

namespace Tests\Feature\PropertyBased;

use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature: geosand-quarry-system, Property 15: Password hashing
 * 
 * For any user record in the database, the password field should contain 
 * a hashed value, not plaintext.
 * 
 * Validates: Requirements 7.1
 */
class PasswordHashingPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that all user passwords are hashed, not plaintext.
     *
     * @return void
     */
    public function test_password_hashing_property()
    {
        $this->forAll(
            Generator\string()
        )
        ->then(function ($password) {
            // Skip empty or very short passwords as they're invalid
            if (strlen($password) < 1) {
                return;
            }

            // Create a user with the generated password
            $user = User::factory()->create([
                'password' => $password,
            ]);

            // Retrieve the user from database
            $userFromDb = User::find($user->id);

            // Property: The stored password should NOT be plaintext
            $this->assertNotEquals(
                $password,
                $userFromDb->password,
                'Password should be hashed, not stored as plaintext'
            );

            // Property: The stored password should be a valid bcrypt hash
            $this->assertTrue(
                Hash::check($password, $userFromDb->password),
                'Stored password should be a valid hash that can verify the original password'
            );

            // Property: Bcrypt hashes start with $2y$ and are 60 characters long
            $this->assertMatchesRegularExpression(
                '/^\$2y\$/',
                $userFromDb->password,
                'Password should be a bcrypt hash'
            );

            $this->assertEquals(
                60,
                strlen($userFromDb->password),
                'Bcrypt hash should be 60 characters long'
            );
        });
    }
}
