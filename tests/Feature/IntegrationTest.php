<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Quarry;
use App\Models\Extraction;
use App\Models\Report;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users for each role
        $this->admin = User::factory()->create([
            'username' => 'admin_test',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->operator = User::factory()->create([
            'username' => 'operator_test',
            'email' => 'operator@test.com',
            'role' => 'operator',
            'is_active' => true,
        ]);

        $this->checkpoint = User::factory()->create([
            'username' => 'checkpoint_test',
            'email' => 'checkpoint@test.com',
            'role' => 'checkpoint',
            'is_active' => true,
        ]);

        // Create test quarries
        $this->activeQuarry = Quarry::factory()->create([
            'name' => 'Test Active Quarry',
            'latitude' => 12.7000,
            'longitude' => 121.4000,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->inactiveQuarry = Quarry::factory()->create([
            'name' => 'Test Inactive Quarry',
            'latitude' => 12.7100,
            'longitude' => 121.4100,
            'is_active' => false,
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * Test complete viewer workflow
     * Requirements: 1.1, 1.2, 1.3, 1.4, 1.5
     */
    public function test_viewer_can_access_public_map_and_submit_report(): void
    {
        // Test public access to quarries (no authentication)
        $response = $this->getJson('/api/quarries');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'quarries' => [
                        '*' => ['id', 'name', 'latitude', 'longitude', 'is_active']
                    ]
                ]
            ]);

        // Verify only active quarries are returned
        $quarries = $response->json('data.quarries');
        foreach ($quarries as $quarry) {
            $this->assertTrue($quarry['is_active']);
        }

        // Test public report submission with valid data
        $validReport = [
            'reporter_name' => 'John Doe',
            'reporter_email' => 'john@example.com',
            'description' => 'Observed unusual activity at quarry site',
            'quarry_id' => $this->activeQuarry->id,
        ];

        $response = $this->postJson('/api/reports', $validReport);
        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'message', 'data' => ['report']]);

        // Verify report was stored
        $this->assertDatabaseHas('reports', [
            'reporter_name' => 'John Doe',
            'description' => 'Observed unusual activity at quarry site',
        ]);

        // Test report submission with empty required fields
        $invalidReport = [
            'reporter_name' => '',
            'description' => '',
        ];

        $response = $this->postJson('/api/reports', $invalidReport);
        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    /**
     * Test complete operator workflow
     * Requirements: 2.1, 2.2, 2.3, 2.4, 2.5
     */
    public function test_operator_complete_workflow(): void
    {
        // Test authentication
        $response = $this->postJson('/api/login', [
            'email' => 'operator@test.com',
            'password' => 'password',
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['token', 'user']]);

        $token = $response->json('data.token');

        // Test extraction submission with valid data
        $extractionData = [
            'quarry_id' => $this->activeQuarry->id,
            'extraction_date' => '2025-12-08',
            'extraction_time' => '10:30:00',
            'quantity' => 150.50,
            'unit' => 'cubic meters',
            'notes' => 'Regular extraction',
        ];

        $response = $this->withToken($token)->postJson('/api/extractions', $extractionData);
        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'message', 'data' => ['extraction']]);

        // Verify extraction status is "pending"
        $extractionId = $response->json('data.extraction.id');
        $this->assertDatabaseHas('extractions', [
            'id' => $extractionId,
            'status' => 'pending',
            'operator_id' => $this->operator->id,
        ]);

        // Test extraction submission with missing required fields
        $invalidData = [
            'quarry_id' => $this->activeQuarry->id,
            'quantity' => 100,
            // Missing extraction_date and extraction_time
        ];

        $response = $this->withToken($token)->postJson('/api/extractions', $invalidData);
        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors']);

        // Test extraction submission to inactive quarry
        $inactiveQuarryData = [
            'quarry_id' => $this->inactiveQuarry->id,
            'extraction_date' => '2025-12-08',
            'extraction_time' => '10:30:00',
            'quantity' => 100,
            'unit' => 'cubic meters',
        ];

        $response = $this->withToken($token)->postJson('/api/extractions', $inactiveQuarryData);
        $response->assertStatus(422);

        // Test viewing submission history
        $response = $this->withToken($token)->getJson('/api/extractions');
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['extractions']]);

        // Verify operator only sees their own extractions
        $extractionsData = $response->json('data.extractions');
        // Handle paginated response
        $extractions = $extractionsData['data'] ?? $extractionsData;
        foreach ($extractions as $extraction) {
            $this->assertEquals($this->operator->id, $extraction['operator_id']);
        }
    }

    /**
     * Test complete checkpoint workflow
     * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5
     */
    public function test_checkpoint_complete_workflow(): void
    {
        // Create a pending extraction
        $extraction = Extraction::factory()->create([
            'quarry_id' => $this->activeQuarry->id,
            'operator_id' => $this->operator->id,
            'status' => 'pending',
        ]);

        // Test checkpoint authentication
        $response = $this->postJson('/api/login', [
            'email' => 'checkpoint@test.com',
            'password' => 'password',
        ]);
        $response->assertStatus(200);
        $token = $response->json('data.token');

        // Test viewing pending extractions
        $response = $this->withToken($token)->getJson('/api/extractions/pending');
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['extractions']]);

        // Verify only pending extractions are returned
        $extractionsData = $response->json('data.extractions');
        // Handle paginated response
        $extractions = $extractionsData['data'] ?? $extractionsData;
        foreach ($extractions as $ext) {
            $this->assertEquals('pending', $ext['status']);
        }

        // Test verification
        $response = $this->withToken($token)->postJson("/api/extractions/{$extraction->id}/verify");
        $response->assertStatus(200);

        // Verify extraction status updated
        $this->assertDatabaseHas('extractions', [
            'id' => $extraction->id,
            'status' => 'verified',
            'verified_by' => $this->checkpoint->id,
        ]);

        // Verify notification created for operator
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->operator->id,
            'type' => 'extraction_verified',
        ]);

        // Test rejection with reason
        $extraction2 = Extraction::factory()->create([
            'quarry_id' => $this->activeQuarry->id,
            'operator_id' => $this->operator->id,
            'status' => 'pending',
        ]);

        $response = $this->withToken($token)->postJson("/api/extractions/{$extraction2->id}/reject", [
            'rejection_reason' => 'Quantity seems incorrect',
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('extractions', [
            'id' => $extraction2->id,
            'status' => 'rejected',
            'rejection_reason' => 'Quantity seems incorrect',
        ]);

        // Test rejection without reason (should fail)
        $extraction3 = Extraction::factory()->create([
            'quarry_id' => $this->activeQuarry->id,
            'operator_id' => $this->operator->id,
            'status' => 'pending',
        ]);

        $response = $this->withToken($token)->postJson("/api/extractions/{$extraction3->id}/reject", []);
        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    /**
     * Test complete admin workflow
     * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 5.1, 5.2, 5.3, 5.4, 6.1, 6.2, 6.3, 6.4, 6.5
     */
    public function test_admin_complete_workflow(): void
    {
        // Test admin authentication
        $response = $this->postJson('/api/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);
        $response->assertStatus(200);
        $token = $response->json('data.token');

        // Test user management - create user
        $newUserData = [
            'username' => 'new_operator',
            'email' => 'newop@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'operator',
        ];

        $response = $this->withToken($token)->postJson('/api/users', $newUserData);
        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'message', 'data' => ['user']]);

        $newUserId = $response->json('data.user.id');

        // Test user listing
        $response = $this->withToken($token)->getJson('/api/users');
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['users']]);

        // Test user update
        $response = $this->withToken($token)->putJson("/api/users/{$newUserId}", [
            'username' => 'updated_operator',
            'email' => 'newop@test.com',
            'role' => 'operator',
        ]);
        $response->assertStatus(200);

        // Test user deactivation
        $response = $this->withToken($token)->deleteJson("/api/users/{$newUserId}");
        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $newUserId,
            'is_active' => false,
        ]);

        // Test quarry management - create quarry
        $quarryData = [
            'name' => 'New Test Quarry',
            'latitude' => 12.7050,
            'longitude' => 121.4050,
            'description' => 'Test quarry description',
        ];

        $response = $this->withToken($token)->postJson('/api/quarries', $quarryData);
        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'message', 'data' => ['quarry']]);

        $quarryId = $response->json('data.quarry.id');

        // Test quarry coordinate validation (outside Bongabong)
        $invalidQuarryData = [
            'name' => 'Invalid Quarry',
            'latitude' => 14.5995, // Manila coordinates
            'longitude' => 120.9842,
        ];

        $response = $this->withToken($token)->postJson('/api/quarries', $invalidQuarryData);
        $response->assertStatus(422);

        // Test quarry update
        $response = $this->withToken($token)->putJson("/api/quarries/{$quarryId}", [
            'name' => 'Updated Quarry Name',
            'latitude' => 12.7050,
            'longitude' => 121.4050,
        ]);
        $response->assertStatus(200);

        // Test quarry deactivation
        $response = $this->withToken($token)->deleteJson("/api/quarries/{$quarryId}");
        $response->assertStatus(200);

        $this->assertDatabaseHas('quarries', [
            'id' => $quarryId,
            'is_active' => false,
        ]);

        // Test analytics - create some test data
        Extraction::factory()->count(5)->create([
            'quarry_id' => $this->activeQuarry->id,
            'operator_id' => $this->operator->id,
            'status' => 'verified',
            'verified_by' => $this->checkpoint->id,
        ]);

        // Test extraction volume analytics
        $response = $this->withToken($token)->getJson('/api/analytics/extractions');
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data']);

        // Test verification rates analytics
        $response = $this->withToken($token)->getJson('/api/analytics/verifications');
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data']);

        // Test extraction by quarry analytics
        $response = $this->withToken($token)->getJson('/api/analytics/quarries');
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data']);

        // Test analytics with date filtering
        $response = $this->withToken($token)->getJson('/api/analytics/extractions?start_date=2025-12-01&end_date=2025-12-31');
        $response->assertStatus(200);

        // Test report management
        $report = Report::factory()->create();

        $response = $this->withToken($token)->getJson('/api/reports');
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['reports']]);

        $response = $this->withToken($token)->putJson("/api/reports/{$report->id}/status", [
            'status' => 'resolved',
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'resolved',
        ]);
    }

    /**
     * Test authentication and authorization flows
     * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5
     */
    public function test_authentication_and_authorization_flows(): void
    {
        // Test login with valid credentials
        $response = $this->postJson('/api/login', [
            'email' => 'operator@test.com',
            'password' => 'password',
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['token', 'user']]);

        $token = $response->json('data.token');

        // Test login with invalid credentials
        $response = $this->postJson('/api/login', [
            'email' => 'operator@test.com',
            'password' => 'wrongpassword',
        ]);
        $response->assertStatus(401);

        // Test accessing protected endpoint without token
        $response = $this->getJson('/api/extractions');
        $response->assertStatus(401);

        // Test accessing protected endpoint with valid token
        $response = $this->withToken($token)->getJson('/api/extractions');
        $response->assertStatus(200);

        // Test role-based access control - operator trying to access admin endpoint
        $response = $this->withToken($token)->getJson('/api/users');
        $response->assertStatus(403);

        // Test role-based access control - operator trying to verify extraction
        $extraction = Extraction::factory()->create([
            'quarry_id' => $this->activeQuarry->id,
            'operator_id' => $this->operator->id,
            'status' => 'pending',
        ]);

        $response = $this->withToken($token)->postJson("/api/extractions/{$extraction->id}/verify");
        $response->assertStatus(403);

        // Test logout
        $response = $this->withToken($token)->postJson('/api/logout');
        $response->assertStatus(200);

        // Note: In testing environment, we need to create a fresh request to test token invalidation
        // The token should be revoked from the database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->operator->id,
            'tokenable_type' => 'App\\Models\\User',
        ]);

        // Test inactive user cannot login
        $inactiveUser = User::factory()->create([
            'email' => 'inactive@test.com',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactive@test.com',
            'password' => 'password',
        ]);
        $response->assertStatus(401);
    }

    /**
     * Test notification system
     * Requirements: 9.1, 9.2, 9.3, 9.4, 9.5
     */
    public function test_notification_system(): void
    {
        // Operator submits extraction
        $extractionData = [
            'quarry_id' => $this->activeQuarry->id,
            'extraction_date' => '2025-12-08',
            'extraction_time' => '10:30:00',
            'quantity' => 100,
            'unit' => 'cubic meters',
        ];

        $response = $this->actingAs($this->operator, 'sanctum')
            ->postJson('/api/extractions', $extractionData);
        $response->assertStatus(201);
        $extractionId = $response->json('data.extraction.id');

        // Checkpoint verifies extraction
        $response = $this->actingAs($this->checkpoint, 'sanctum')
            ->postJson("/api/extractions/{$extractionId}/verify");
        $response->assertStatus(200);

        // Check operator received notification
        $response = $this->actingAs($this->operator, 'sanctum')
            ->getJson('/api/notifications');
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['notifications']]);

        $notificationsData = $response->json('data.notifications');
        // Handle paginated response
        $notifications = $notificationsData['data'] ?? $notificationsData;
        $this->assertGreaterThan(0, count($notifications));

        // Test mark as read
        $notificationId = $notifications[0]['id'];
        $response = $this->actingAs($this->operator, 'sanctum')
            ->putJson("/api/notifications/{$notificationId}/read");
        $response->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'is_read' => true,
        ]);

        // Test mark all as read
        $response = $this->actingAs($this->operator, 'sanctum')
            ->putJson('/api/notifications/read-all');
        $response->assertStatus(200);

        // Verify all notifications are marked as read
        $unreadCount = Notification::where('user_id', $this->operator->id)
            ->where('is_read', false)
            ->count();
        $this->assertEquals(0, $unreadCount);
    }

    /**
     * Test data persistence and retrieval
     * Requirements: All
     */
    public function test_data_persistence_and_retrieval(): void
    {
        // Create extraction with all relationships
        $extraction = Extraction::factory()->create([
            'quarry_id' => $this->activeQuarry->id,
            'operator_id' => $this->operator->id,
            'status' => 'verified',
            'verified_by' => $this->checkpoint->id,
        ]);

        // Verify relationships are properly loaded
        $this->assertNotNull($extraction->quarry);
        $this->assertNotNull($extraction->operator);
        $this->assertNotNull($extraction->verifier);

        $this->assertEquals($this->activeQuarry->id, $extraction->quarry->id);
        $this->assertEquals($this->operator->id, $extraction->operator->id);
        $this->assertEquals($this->checkpoint->id, $extraction->verifier->id);

        // Test quarry has extractions relationship
        $quarry = Quarry::find($this->activeQuarry->id);
        $this->assertGreaterThan(0, $quarry->extractions->count());

        // Test user has extractions relationship
        $operator = User::find($this->operator->id);
        $this->assertGreaterThan(0, $operator->extractions->count());

        // Test soft delete preserves data
        $userId = $this->operator->id;
        $extractionCount = Extraction::where('operator_id', $userId)->count();

        // Deactivate user
        $this->operator->update(['is_active' => false]);

        // Verify extractions still exist
        $this->assertEquals(
            $extractionCount,
            Extraction::where('operator_id', $userId)->count()
        );

        // Test quarry deactivation preserves data
        $quarryId = $this->activeQuarry->id;
        $extractionCount = Extraction::where('quarry_id', $quarryId)->count();

        // Deactivate quarry
        $this->activeQuarry->update(['is_active' => false]);

        // Verify extractions still exist
        $this->assertEquals(
            $extractionCount,
            Extraction::where('quarry_id', $quarryId)->count()
        );
    }
}
