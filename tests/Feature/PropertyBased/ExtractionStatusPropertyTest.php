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
 * Feature: geosand-quarry-system, Property 5: Extraction status initialization
 * 
 * For any newly created extraction record, the status field should be set 
 * to "pending verification".
 * 
 * Validates: Requirements 2.2
 */
class ExtractionStatusPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that all newly created extractions have status "pending".
     *
     * @return void
     */
    public function test_extraction_status_initialization_property()
    {
        $this->forAll(
            Generator\float(1, 1000),
            Generator\string()
        )
        ->then(function ($quantity, $notes) {
            // Create necessary related models
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create();

            // Create extraction with generated data
            $extraction = Extraction::create([
                'quarry_id' => $quarry->id,
                'operator_id' => $operator->id,
                'extraction_date' => now()->format('Y-m-d'),
                'extraction_time' => '10:00:00',
                'quantity' => $quantity,
                'unit' => 'cubic meters',
                'notes' => $notes,
            ]);

            // Property: Status should be "pending" for newly created extractions
            $this->assertEquals('pending', $extraction->status);
            
            // Verify from database
            $extractionFromDb = Extraction::find($extraction->id);
            $this->assertEquals('pending', $extractionFromDb->status);
        });
    }

    /**
     * Test that extraction status defaults to pending even when not explicitly set.
     *
     * @return void
     */
    public function test_extraction_status_defaults_to_pending()
    {
        $this->forAll(
            Generator\float(1, 1000)
        )
        ->then(function ($quantity) {
            $operator = User::factory()->create(['role' => 'operator']);
            $quarry = Quarry::factory()->create();

            // Create extraction without specifying status
            $extraction = Extraction::create([
                'quarry_id' => $quarry->id,
                'operator_id' => $operator->id,
                'extraction_date' => now()->format('Y-m-d'),
                'extraction_time' => now()->format('H:i:s'),
                'quantity' => $quantity,
            ]);

            // Property: Status should default to "pending"
            $this->assertEquals('pending', $extraction->status);
        });
    }

    /**
     * Test that factory-created extractions also have pending status.
     *
     * @return void
     */
    public function test_factory_created_extractions_have_pending_status()
    {
        $this->forAll(
            Generator\int(1, 10)
        )
        ->then(function ($count) {
            // Create multiple extractions using factory
            $extractions = Extraction::factory()->count($count)->create();

            // Property: All factory-created extractions should have pending status
            foreach ($extractions as $extraction) {
                $this->assertEquals('pending', $extraction->status);
            }
        });
    }
}
