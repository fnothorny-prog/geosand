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
 * Feature: geosand-quarry-system, Property 6: Extraction data validation
 * 
 * For any extraction submission missing required fields (quarry_id, quantity, date, time), 
 * the system should reject the submission and return field-specific validation errors.
 * 
 * Validates: Requirements 2.4
 */
class ExtractionDataValidationPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that extraction submission with missing required fields is rejected.
     *
     * @return void
     */
    public function test_extraction_missing_required_fields_rejected()
    {
        $this->forAll(
            Generator\elements([
                // Missing quarry_id
                ['extraction_date' => '2025-12-08', 'extraction_time' => '10:00:00', 'quantity' => 100, 'unit' => 'cubic meters'],
                // Missing extraction_date
                ['quarry_id' => 1, 'extraction_time' => '10:00:00', 'quantity' => 100, 'unit' => 'cubic meters'],
                // Missing extraction_time
                ['quarry_id' => 1, 'extraction_date' => '2025-12-08', 'quantity' => 100, 'unit' => 'cubic meters'],
                // Missing quantity
                ['quarry_id' => 1, 'extraction_date' => '2025-12-08', 'extraction_time' => '10:00:00', 'unit' => 'cubic meters'],
            ])
        )
        ->then(function ($extractionData) {
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create(['is_active' => true]);

            // Update quarry_id if it exists in the data
            if (isset($extractionData['quarry_id'])) {
                $extractionData['quarry_id'] = $quarry->id;
            }

            // Count extractions before request
            $extractionCountBefore = Extraction::count();

            // Attempt to create extraction with missing required field
            $response = $this->actingAs($operator, 'sanctum')
                ->postJson('/api/extractions', $extractionData);

            // Property: Missing required fields should result in 422 validation error
            $this->assertEquals(422, $response->status());
            
            // Property: No extraction should be created
            $this->assertEquals($extractionCountBefore, Extraction::count());
            
            // Property: Response should contain validation errors
            $response->assertJsonStructure(['message', 'errors']);
        });
    }

    /**
     * Test that extraction submission with invalid data types is rejected.
     *
     * @return void
     */
    public function test_extraction_invalid_data_types_rejected()
    {
        $this->forAll(
            Generator\string()
        )
        ->then(function ($invalidQuantity) {
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create(['is_active' => true]);

            // Count extractions before request
            $extractionCountBefore = Extraction::count();

            // Attempt to create extraction with invalid quantity (non-numeric string)
            $response = $this->actingAs($operator, 'sanctum')
                ->postJson('/api/extractions', [
                    'quarry_id' => $quarry->id,
                    'extraction_date' => '2025-12-08',
                    'extraction_time' => '10:00:00',
                    'quantity' => $invalidQuantity,
                    'unit' => 'cubic meters',
                ]);

            // If the string is numeric, it might pass validation
            if (is_numeric($invalidQuantity) && $invalidQuantity >= 0) {
                // Valid numeric string - should be accepted
                $this->assertContains($response->status(), [201, 422]);
            } else {
                // Property: Invalid quantity should be rejected with 422
                $this->assertEquals(422, $response->status());
                
                // Property: No extraction should be created
                $this->assertEquals($extractionCountBefore, Extraction::count());
                
                // Property: Should have validation error for quantity field
                $response->assertJsonValidationErrors(['quantity']);
            }
        });
    }


    /**
     * Test that extraction submission with negative quantity is rejected.
     *
     * @return void
     */
    public function test_extraction_negative_quantity_rejected()
    {
        $this->forAll(
            Generator\neg()
        )
        ->then(function ($negativeQuantity) {
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create(['is_active' => true]);

            // Count extractions before request
            $extractionCountBefore = Extraction::count();

            // Attempt to create extraction with negative quantity
            $response = $this->actingAs($operator, 'sanctum')
                ->postJson('/api/extractions', [
                    'quarry_id' => $quarry->id,
                    'extraction_date' => '2025-12-08',
                    'extraction_time' => '10:00:00',
                    'quantity' => $negativeQuantity,
                    'unit' => 'cubic meters',
                ]);

            // Property: Negative quantity should be rejected with 422
            $this->assertEquals(422, $response->status());
            
            // Property: No extraction should be created
            $this->assertEquals($extractionCountBefore, Extraction::count());
            
            // Property: Should have validation error for quantity field
            $response->assertJsonValidationErrors(['quantity']);
        });
    }

    /**
     * Test that extraction submission with invalid date format is rejected.
     *
     * @return void
     */
    public function test_extraction_invalid_date_rejected()
    {
        $this->forAll(
            Generator\string()
        )
        ->then(function ($invalidDate) {
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create(['is_active' => true]);

            // Count extractions before request
            $extractionCountBefore = Extraction::count();

            // Attempt to create extraction with invalid date
            $response = $this->actingAs($operator, 'sanctum')
                ->postJson('/api/extractions', [
                    'quarry_id' => $quarry->id,
                    'extraction_date' => $invalidDate,
                    'extraction_time' => '10:00:00',
                    'quantity' => 100,
                    'unit' => 'cubic meters',
                ]);

            // Check if the string is a valid date format
            $isValidDate = strtotime($invalidDate) !== false;

            if ($isValidDate) {
                // Valid date format - might be accepted
                $this->assertContains($response->status(), [201, 422]);
            } else {
                // Property: Invalid date should be rejected with 422
                $this->assertEquals(422, $response->status());
                
                // Property: No extraction should be created
                $this->assertEquals($extractionCountBefore, Extraction::count());
                
                // Property: Should have validation error for extraction_date field
                $response->assertJsonValidationErrors(['extraction_date']);
            }
        });
    }


    /**
     * Test that extraction submission with non-existent quarry is rejected.
     *
     * @return void
     */
    public function test_extraction_nonexistent_quarry_rejected()
    {
        $this->forAll(
            Generator\choose(999999, 9999999)
        )
        ->then(function ($nonExistentQuarryId) {
            $operator = User::factory()->create(['role' => 'operator']);

            // Ensure this quarry ID doesn't exist
            $this->assertDatabaseMissing('quarries', ['id' => $nonExistentQuarryId]);

            // Count extractions before request
            $extractionCountBefore = Extraction::count();

            // Attempt to create extraction with non-existent quarry
            $response = $this->actingAs($operator, 'sanctum')
                ->postJson('/api/extractions', [
                    'quarry_id' => $nonExistentQuarryId,
                    'extraction_date' => '2025-12-08',
                    'extraction_time' => '10:00:00',
                    'quantity' => 100,
                    'unit' => 'cubic meters',
                ]);

            // Property: Non-existent quarry should be rejected with 422
            $this->assertEquals(422, $response->status());
            
            // Property: No extraction should be created
            $this->assertEquals($extractionCountBefore, Extraction::count());
            
            // Property: Should have validation error for quarry_id field
            $response->assertJsonValidationErrors(['quarry_id']);
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
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create(['is_active' => true]);

            // Count extractions before request
            $extractionCountBefore = Extraction::count();

            // Attempt to create extraction with multiple validation errors
            $response = $this->actingAs($operator, 'sanctum')
                ->postJson('/api/extractions', [
                    'quarry_id' => 'invalid',
                    'extraction_date' => 'not-a-date',
                    'extraction_time' => 'not-a-time',
                    'quantity' => 'not-a-number',
                    'unit' => '',
                ]);

            // Property: Multiple validation errors should be rejected
            $this->assertEquals(422, $response->status());
            
            // Property: No extraction should be created
            $this->assertEquals($extractionCountBefore, Extraction::count());
            
            // Property: Should have validation errors
            $response->assertJsonStructure(['message', 'errors']);
        });
    }
}
