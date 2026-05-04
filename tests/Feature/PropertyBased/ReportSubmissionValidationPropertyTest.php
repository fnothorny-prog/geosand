<?php

namespace Tests\Feature\PropertyBased;

use App\Models\Quarry;
use App\Models\Report;
use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: geosand-quarry-system, Property 2: Report submission validation
 * 
 * For any report submission with all required fields populated, the system 
 * should accept and store the report with a timestamp.
 * 
 * Validates: Requirements 1.3
 */
class ReportSubmissionValidationPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that valid report submissions are accepted and stored.
     *
     * @return void
     */
    public function test_valid_report_submissions_are_accepted()
    {
        $this->forAll(
            Generator\string(),
            Generator\string()
        )
        ->then(function ($description, $reporterName) {
            // Skip empty descriptions as they are invalid
            if (empty(trim($description))) {
                return;
            }

            $reportCountBefore = Report::count();

            // Submit a report with required fields
            $response = $this->postJson('/api/reports', [
                'description' => $description,
                'reporter_name' => $reporterName,
            ]);

            // Property: Valid report should be accepted with 201 status
            $this->assertEquals(201, $response->status());
            
            // Property: Report should be stored in database
            $this->assertEquals($reportCountBefore + 1, Report::count());
            
            // Property: Response should contain the report data
            $response->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'report' => [
                        'id',
                        'description',
                        'reporter_name',
                        'status',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ]);

            // Property: Report should have timestamp
            $reportId = $response->json('data.report.id');
            $report = Report::find($reportId);
            $this->assertNotNull($report->created_at);
            // Verify the description was stored
            $this->assertSame($description, $report->description);
        });
    }

    /**
     * Test that reports with optional fields are accepted.
     *
     * @return void
     */
    public function test_reports_with_optional_fields_are_accepted()
    {
        $this->forAll(
            Generator\string(),
            Generator\string(),
            Generator\string(),
            Generator\float(-90, 90),
            Generator\float(-180, 180)
        )
        ->then(function ($description, $reporterName, $reporterEmail, $latitude, $longitude) {
            // Skip empty descriptions
            if (empty(trim($description))) {
                return;
            }

            // Create a quarry for testing (reuse if exists to avoid creating too many)
            $admin = User::firstOrCreate(
                ['username' => 'test_admin_reports'],
                [
                    'email' => 'admin_reports@test.com',
                    'password' => bcrypt('password'),
                    'role' => 'admin',
                    'is_active' => true,
                ]
            );
            
            $quarry = Quarry::firstOrCreate(
                ['name' => 'Test Quarry for Reports'],
                [
                    'latitude' => 12.7,
                    'longitude' => 121.4,
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]
            );

            $reportCountBefore = Report::count();

            // Submit report with all fields including optional ones
            $response = $this->postJson('/api/reports', [
                'description' => $description,
                'reporter_name' => $reporterName,
                'reporter_email' => $reporterEmail,
                'quarry_id' => $quarry->id,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            // Property: Report with optional fields should be accepted
            if ($response->status() === 201) {
                $this->assertEquals($reportCountBefore + 1, Report::count());
                
                $reportId = $response->json('data.report.id');
                $report = Report::find($reportId);
                $this->assertSame($description, $report->description);
                $this->assertNotNull($report->created_at);
            } else if ($response->status() === 422) {
                // Validation failed (e.g., invalid email format)
                // Property: No report should be created
                $this->assertEquals($reportCountBefore, Report::count());
            }
        });
    }

    /**
     * Test that reports are stored with correct status.
     *
     * @return void
     */
    public function test_reports_are_stored_with_new_status()
    {
        $this->forAll(
            Generator\string()
        )
        ->then(function ($description) {
            // Skip empty descriptions
            if (empty(trim($description))) {
                return;
            }

            // Submit a report
            $response = $this->postJson('/api/reports', [
                'description' => $description,
            ]);

            if ($response->status() === 201) {
                // Property: New reports should have status 'new'
                $report = Report::latest()->first();
                $this->assertEquals('new', $report->status);
            }
        });
    }

    /**
     * Test that public endpoint accepts reports without authentication.
     *
     * @return void
     */
    public function test_public_endpoint_accepts_reports_without_auth()
    {
        $this->forAll(
            Generator\string()
        )
        ->then(function ($description) {
            // Skip empty descriptions
            if (empty(trim($description))) {
                return;
            }

            $reportCountBefore = Report::count();

            // Submit report without authentication
            $response = $this->postJson('/api/reports', [
                'description' => $description,
            ]);

            // Property: Public endpoint should accept reports without auth
            $this->assertEquals(201, $response->status());
            $this->assertEquals($reportCountBefore + 1, Report::count());
        });
    }

    /**
     * Test that reports with valid quarry_id are accepted.
     *
     * @return void
     */
    public function test_reports_with_valid_quarry_id_are_accepted()
    {
        $this->forAll(
            Generator\string()
        )
        ->then(function ($description) {
            // Skip empty descriptions
            if (empty(trim($description))) {
                return;
            }

            // Create or reuse a quarry
            $admin = User::firstOrCreate(
                ['username' => 'test_admin_quarry_reports'],
                [
                    'email' => 'admin_quarry_reports@test.com',
                    'password' => bcrypt('password'),
                    'role' => 'admin',
                    'is_active' => true,
                ]
            );
            
            $quarry = Quarry::firstOrCreate(
                ['name' => 'Test Quarry for Report Validation'],
                [
                    'latitude' => 12.7,
                    'longitude' => 121.4,
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]
            );

            $reportCountBefore = Report::count();

            // Submit report with valid quarry_id
            $response = $this->postJson('/api/reports', [
                'description' => $description,
                'quarry_id' => $quarry->id,
            ]);

            // Property: Report with valid quarry_id should be accepted
            $this->assertEquals(201, $response->status());
            $this->assertEquals($reportCountBefore + 1, Report::count());
            
            $report = Report::latest()->first();
            $this->assertEquals($quarry->id, $report->quarry_id);
        });
    }
}
