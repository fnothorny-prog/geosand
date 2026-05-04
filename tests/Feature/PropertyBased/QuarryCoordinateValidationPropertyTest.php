<?php

namespace Tests\Feature\PropertyBased;

use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: geosand-quarry-system, Property 12: Quarry coordinate validation
 * 
 * For any quarry creation with coordinates outside the valid range for 
 * Bongabong, Oriental Mindoro, the system should reject the submission 
 * with a validation error.
 * 
 * Validates: Requirements 5.2
 */
class QuarryCoordinateValidationPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    // Bongabong, Oriental Mindoro boundaries
    private const BONGABONG_MIN_LAT = 12.6000;
    private const BONGABONG_MAX_LAT = 12.8000;
    private const BONGABONG_MIN_LNG = 121.3000;
    private const BONGABONG_MAX_LNG = 121.5000;

    /**
     * Test that coordinates within Bongabong boundaries are accepted.
     *
     * @return void
     */
    public function test_valid_bongabong_coordinates_are_accepted()
    {
        $this->forAll(
            Generator\choose((int)(self::BONGABONG_MIN_LAT * 10000), (int)(self::BONGABONG_MAX_LAT * 10000)),
            Generator\choose((int)(self::BONGABONG_MIN_LNG * 10000), (int)(self::BONGABONG_MAX_LNG * 10000))
        )
        ->then(function ($latInt, $lngInt) {
            $latitude = $latInt / 10000;
            $longitude = $lngInt / 10000;

            // Create admin user
            $admin = User::factory()->create(['role' => 'admin']);

            // Attempt to create quarry with coordinates within boundaries
            $response = $this->actingAs($admin, 'sanctum')->postJson('/api/quarries', [
                'name' => 'Test Quarry ' . uniqid(),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'description' => 'Test quarry description',
            ]);

            // Property: Coordinates within Bongabong boundaries should be accepted
            $response->assertStatus(201);
            $response->assertJsonStructure([
                'success',
                'data' => [
                    'quarry' => [
                        'id',
                        'name',
                        'latitude',
                        'longitude',
                        'is_active',
                    ]
                ]
            ]);

            $this->assertTrue($response->json('success'));
        });
    }

    /**
     * Test that coordinates outside Bongabong boundaries are rejected.
     *
     * @return void
     */
    public function test_invalid_coordinates_are_rejected()
    {
        $this->forAll(
            Generator\choose(-900000, 900000), // Latitude * 10000
            Generator\choose(-1800000, 1800000) // Longitude * 10000
        )
        ->then(function ($latInt, $lngInt) {
            $latitude = $latInt / 10000;
            $longitude = $lngInt / 10000;

            // Skip if coordinates are within valid boundaries
            if ($this->isWithinBongabongBoundaries($latitude, $longitude)) {
                return;
            }

            // Create admin user
            $admin = User::factory()->create(['role' => 'admin']);

            // Attempt to create quarry with coordinates outside boundaries
            $response = $this->actingAs($admin, 'sanctum')->postJson('/api/quarries', [
                'name' => 'Test Quarry ' . uniqid(),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'description' => 'Test quarry description',
            ]);

            // Property: Coordinates outside Bongabong boundaries should be rejected
            $response->assertStatus(422);
            $this->assertFalse($response->json('success'));
            
            // Should have validation errors for coordinates
            $errors = $response->json('errors');
            $this->assertTrue(
                isset($errors['latitude']) || isset($errors['longitude']),
                'Expected validation errors for coordinates outside boundaries'
            );
        });
    }

    /**
     * Test that quarry updates also validate coordinates.
     *
     * @return void
     */
    public function test_coordinate_validation_on_update()
    {
        $this->forAll(
            Generator\choose(-900000, 900000), // Latitude * 10000
            Generator\choose(-1800000, 1800000) // Longitude * 10000
        )
        ->then(function ($latInt, $lngInt) {
            $latitude = $latInt / 10000;
            $longitude = $lngInt / 10000;

            // Create admin user
            $admin = User::factory()->create(['role' => 'admin']);

            // Create a quarry with valid coordinates first
            $quarry = \App\Models\Quarry::factory()->create([
                'latitude' => 12.7000,
                'longitude' => 121.4000,
                'created_by' => $admin->id,
            ]);

            // Attempt to update with new coordinates
            $response = $this->actingAs($admin, 'sanctum')->putJson("/api/quarries/{$quarry->id}", [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            if ($this->isWithinBongabongBoundaries($latitude, $longitude)) {
                // Property: Valid coordinates should be accepted on update
                $response->assertStatus(200);
                $this->assertTrue($response->json('success'));
            } else {
                // Property: Invalid coordinates should be rejected on update
                $response->assertStatus(422);
                $this->assertFalse($response->json('success'));
            }
        });
    }

    /**
     * Check if coordinates are within Bongabong boundaries.
     *
     * @param float $latitude
     * @param float $longitude
     * @return bool
     */
    private function isWithinBongabongBoundaries($latitude, $longitude)
    {
        return $latitude >= self::BONGABONG_MIN_LAT
            && $latitude <= self::BONGABONG_MAX_LAT
            && $longitude >= self::BONGABONG_MIN_LNG
            && $longitude <= self::BONGABONG_MAX_LNG;
    }
}
