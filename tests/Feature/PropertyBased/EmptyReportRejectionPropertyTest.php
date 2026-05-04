<?php

namespace Tests\Feature\PropertyBased;

use App\Models\Report;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: geosand-quarry-system, Property 3: Empty report rejection
 * 
 * For any report submission with empty required fields, the system should 
 * reject the submission and return specific validation errors.
 * 
 * Validates: Requirements 1.4
 */
class EmptyReportRejectionPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that reports with empty description are rejected.
     *
     * @return void
     */
    public function test_reports_with_empty_description_are_rejected()
    {
        $this->forAll(
            Generator\elements(['', '   ', "\t", "\n", "  \n  \t  "])
        )
        ->then(function ($emptyDescription) {
            $reportCountBefore = Report::count();

            // Attempt to submit report with empty description
            $response = $this->postJson('/api/reports', [
                'description' => $emptyDescription,
            ]);

            // Property: Empty description should be rejected with 422 status
            $this->assertEquals(422, $response->status());
            
            // Property: No report should be created
            $this->assertEquals($reportCountBefore, Report::count());
            
            // Property: Response should contain validation errors
            $response->assertJsonStructure([
                'message',
                'errors' => ['description']
            ]);
        });
    }

    /**
     * Test that reports without description field are rejected.
     *
     * @return void
     */
    public function test_reports_without_description_field_are_rejected()
    {
        $reportCountBefore = Report::count();

        // Attempt to submit report without description field
        $response = $this->postJson('/api/reports', [
            'reporter_name' => 'John Doe',
            'reporter_email' => 'john@example.com',
        ]);

        // Property: Missing description should be rejected with 422 status
        $this->assertEquals(422, $response->status());
        
        // Property: No report should be created
        $this->assertEquals($reportCountBefore, Report::count());
        
        // Property: Response should contain validation error for description
        $response->assertJsonValidationErrors(['description']);
    }

    /**
     * Test that validation errors are specific to the field.
     *
     * @return void
     */
    public function test_validation_errors_are_field_specific()
    {
        $this->forAll(
            Generator\elements([
                ['description' => ''], // empty description
                ['description' => '   '], // whitespace description
                ['reporter_email' => 'invalid-email', 'description' => 'Valid description'], // invalid email
                ['quarry_id' => 99999, 'description' => 'Valid description'], // non-existent quarry
                ['latitude' => 100, 'description' => 'Valid description'], // invalid latitude
                ['longitude' => 200, 'description' => 'Valid description'], // invalid longitude
            ])
        )
        ->then(function ($invalidData) {
            $reportCountBefore = Report::count();

            // Attempt to submit report with invalid data
            $response = $this->postJson('/api/reports', $invalidData);

            // Property: Invalid data should be rejected with 422 status
            $this->assertEquals(422, $response->status());
            
            // Property: No report should be created
            $this->assertEquals($reportCountBefore, Report::count());
            
            // Property: Response should contain specific validation errors
            $response->assertJsonStructure([
                'message',
                'errors'
            ]);

            // Property: Errors should be an object/array
            $this->assertIsArray($response->json('errors'));
        });
    }

    /**
     * Test that multiple validation errors are returned together.
     *
     * @return void
     */
    public function test_multiple_validation_errors_returned_together()
    {
        $reportCountBefore = Report::count();

        // Attempt to submit report with multiple invalid fields
        $response = $this->postJson('/api/reports', [
            'description' => '', // empty (invalid)
            'reporter_email' => 'not-an-email', // invalid format
            'latitude' => 100, // out of range
            'longitude' => 200, // out of range
        ]);

        // Property: Multiple validation errors should be returned
        $this->assertEquals(422, $response->status());
        
        // Property: No report should be created
        $this->assertEquals($reportCountBefore, Report::count());
        
        // Property: Response should contain errors for all invalid fields
        $errors = $response->json('errors');
        $this->assertArrayHasKey('description', $errors);
        $this->assertArrayHasKey('reporter_email', $errors);
        $this->assertArrayHasKey('latitude', $errors);
        $this->assertArrayHasKey('longitude', $errors);
    }

    /**
     * Test that validation prevents database operations.
     *
     * @return void
     */
    public function test_validation_prevents_database_operations()
    {
        $this->forAll(
            Generator\choose(1, 100)
        )
        ->then(function ($iteration) {
            $reportCountBefore = Report::count();

            // Attempt to submit invalid report
            $response = $this->postJson('/api/reports', [
                'description' => '', // Invalid
                'reporter_name' => 'Test Reporter ' . $iteration,
            ]);

            // Property: Validation should prevent database insert
            $this->assertEquals(422, $response->status());
            $this->assertEquals($reportCountBefore, Report::count());
            
            // Property: No report with this reporter_name should exist
            $this->assertDatabaseMissing('reports', [
                'reporter_name' => 'Test Reporter ' . $iteration,
            ]);
        });
    }

    /**
     * Test that empty string and null are both rejected for required fields.
     *
     * @return void
     */
    public function test_empty_and_null_both_rejected_for_required_fields()
    {
        $this->forAll(
            Generator\elements([null, '', '   '])
        )
        ->then(function ($invalidValue) {
            $reportCountBefore = Report::count();

            // Attempt to submit report with invalid description
            $response = $this->postJson('/api/reports', [
                'description' => $invalidValue,
            ]);

            // Property: Both null and empty should be rejected
            $this->assertEquals(422, $response->status());
            $this->assertEquals($reportCountBefore, Report::count());
            $response->assertJsonValidationErrors(['description']);
        });
    }
}
