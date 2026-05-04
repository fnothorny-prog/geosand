<?php

namespace Tests\Feature\PropertyBased;

use App\Models\Extraction;
use App\Models\Notification;
use App\Models\Quarry;
use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: geosand-quarry-system, Property 18: Notification creation on verification
 * 
 * For any extraction verification or rejection, the system should create a 
 * notification record for the operator who submitted the extraction.
 * 
 * Validates: Requirements 9.2
 */
class NotificationCreationPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    /**
     * Test that verifying an extraction creates a notification for the operator.
     *
     * @return void
     */
    public function test_notification_created_on_extraction_verification()
    {
        $this->forAll(
            Generator\float(1, 1000),
            Generator\string()
        )
        ->then(function ($quantity, $notes) {
            // Create necessary users
            $operator = User::factory()->create(['role' => 'operator']);
            $checkpoint = User::factory()->create(['role' => 'checkpoint']);
            $quarry = Quarry::factory()->create();

            // Create a pending extraction
            $extraction = Extraction::create([
                'quarry_id' => $quarry->id,
                'operator_id' => $operator->id,
                'extraction_date' => now()->format('Y-m-d'),
                'extraction_time' => '10:00:00',
                'quantity' => $quantity,
                'unit' => 'cubic meters',
                'status' => 'pending',
                'notes' => $notes,
            ]);

            // Count notifications before verification
            $notificationCountBefore = Notification::where('user_id', $operator->id)->count();

            // Verify the extraction via API
            $response = $this->actingAs($checkpoint, 'sanctum')
                ->postJson("/api/extractions/{$extraction->id}/verify");

            $response->assertStatus(200);

            // Property: A notification should be created for the operator
            $notificationCountAfter = Notification::where('user_id', $operator->id)->count();
            $this->assertEquals($notificationCountBefore + 1, $notificationCountAfter);

            // Verify notification details
            $notification = Notification::where('user_id', $operator->id)
                ->where('related_id', $extraction->id)
                ->where('related_type', 'extraction')
                ->latest()
                ->first();

            $this->assertNotNull($notification);
            $this->assertEquals('extraction_verified', $notification->type);
            $this->assertFalse($notification->is_read);
        });
    }

    /**
     * Test that rejecting an extraction creates a notification for the operator.
     *
     * @return void
     */
    public function test_notification_created_on_extraction_rejection()
    {
        $this->forAll(
            Generator\float(1, 1000),
            Generator\string()
        )
        ->then(function ($quantity, $rejectionReason) {
            // Skip empty rejection reasons as they're invalid
            if (empty(trim($rejectionReason))) {
                return;
            }

            // Create necessary users
            $operator = User::factory()->create(['role' => 'operator']);
            $checkpoint = User::factory()->create(['role' => 'checkpoint']);
            $quarry = Quarry::factory()->create();

            // Create a pending extraction
            $extraction = Extraction::create([
                'quarry_id' => $quarry->id,
                'operator_id' => $operator->id,
                'extraction_date' => now()->format('Y-m-d'),
                'extraction_time' => '10:00:00',
                'quantity' => $quantity,
                'unit' => 'cubic meters',
                'status' => 'pending',
            ]);

            // Count notifications before rejection
            $notificationCountBefore = Notification::where('user_id', $operator->id)->count();

            // Reject the extraction via API
            $response = $this->actingAs($checkpoint, 'sanctum')
                ->postJson("/api/extractions/{$extraction->id}/reject", [
                    'rejection_reason' => $rejectionReason,
                ]);

            $response->assertStatus(200);

            // Property: A notification should be created for the operator
            $notificationCountAfter = Notification::where('user_id', $operator->id)->count();
            $this->assertEquals($notificationCountBefore + 1, $notificationCountAfter);

            // Verify notification details
            $notification = Notification::where('user_id', $operator->id)
                ->where('related_id', $extraction->id)
                ->where('related_type', 'extraction')
                ->latest()
                ->first();

            $this->assertNotNull($notification);
            $this->assertEquals('extraction_rejected', $notification->type);
            $this->assertFalse($notification->is_read);
            $this->assertStringContainsString($rejectionReason, $notification->message);
        });
    }

    /**
     * Test that notification is created for the correct operator.
     *
     * @return void
     */
    public function test_notification_sent_to_correct_operator()
    {
        $this->forAll(
            Generator\float(1, 1000)
        )
        ->then(function ($quantity) {
            // Create two operators
            $operator1 = User::factory()->create(['role' => 'operator']);
            $operator2 = User::factory()->create(['role' => 'operator']);
            $checkpoint = User::factory()->create(['role' => 'checkpoint']);
            $quarry = Quarry::factory()->create();

            // Create extraction for operator1
            $extraction = Extraction::create([
                'quarry_id' => $quarry->id,
                'operator_id' => $operator1->id,
                'extraction_date' => now()->format('Y-m-d'),
                'extraction_time' => '10:00:00',
                'quantity' => $quantity,
                'unit' => 'cubic meters',
                'status' => 'pending',
            ]);

            // Verify the extraction
            $this->actingAs($checkpoint, 'sanctum')
                ->postJson("/api/extractions/{$extraction->id}/verify");

            // Property: Only operator1 should receive a notification
            $notification = Notification::where('related_id', $extraction->id)
                ->where('related_type', 'extraction')
                ->first();

            $this->assertNotNull($notification);
            $this->assertEquals($operator1->id, $notification->user_id);

            // Verify operator2 did not receive this notification
            $operator2Notification = Notification::where('user_id', $operator2->id)
                ->where('related_id', $extraction->id)
                ->where('related_type', 'extraction')
                ->first();
            $this->assertNull($operator2Notification);
        });
    }
}
