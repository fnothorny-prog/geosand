<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExtractionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\QuarryController;
use App\Http\Controllers\QuarryDocumentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/test', function () {
    return response()->json(['message' => 'Laravel API is working!']);
});

Route::post('/test-login', function (Request $request) {
    \Log::info('TEST LOGIN ENDPOINT HIT', ['email' => $request->email]);
    
    $user = \App\Models\User::where('email', $request->email)->first();
    
    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }
    
    $passwordCheck = \Hash::check($request->password, $user->password);
    
    return response()->json([
        'user_found' => true,
        'email' => $user->email,
        'password_correct' => $passwordCheck,
        'is_active' => $user->is_active,
    ]);
});

// Authentication routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
Route::post('/register', [AuthController::class, 'register'])->middleware(['auth:sanctum', 'role:admin']);

// Public operator registration (pending admin approval)
Route::post('/operator-register', [AuthController::class, 'operatorRegister']);

// Checkpoint-only extraction routes (must be before generic {id} routes)
Route::middleware(['auth:sanctum', 'role:checkpoint'])->group(function () {
    Route::get('/extractions/pending', [ExtractionController::class, 'pending']);
    Route::post('/extractions/{id}/verify', [ExtractionController::class, 'verify']);
    Route::post('/extractions/{id}/reject', [ExtractionController::class, 'reject']);
});

// Stats route (checkpoint + admin)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/extractions/stats', [ExtractionController::class, 'stats']);
});

// Operator-only extraction routes
Route::post('/extractions', [ExtractionController::class, 'store'])->middleware(['auth:sanctum', 'role:operator']);

// Extraction routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/extractions', [ExtractionController::class, 'index']);
    Route::get('/extractions/{id}', [ExtractionController::class, 'show']);
});

// Quarry routes
Route::get('/quarries', [QuarryController::class, 'index']); // Public - shows active quarries
Route::get('/quarries/{id}', [QuarryController::class, 'show']); // Public

// Admin-only quarry management routes
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/quarries', [QuarryController::class, 'store']);
    Route::put('/quarries/{id}', [QuarryController::class, 'update']);
    Route::delete('/quarries/{id}', [QuarryController::class, 'destroy']);

    // Quarry document deletion (admin only)
    Route::delete('/quarries/{quarryId}/documents/{documentId}', [QuarryDocumentController::class, 'destroy']);
});

// Quarry document upload (admin + operators for their assigned quarry)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/quarries/{quarryId}/documents', [QuarryDocumentController::class, 'store']);
});

// Quarry documents - view (authenticated users)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/quarries/{quarryId}/documents', [QuarryDocumentController::class, 'index']);
    Route::get('/quarries/{quarryId}/documents/{documentId}', [QuarryDocumentController::class, 'show']);
});

// User management routes (Admin only)
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Pending operator registrations
    Route::get('/pending-registrations', [AuthController::class, 'pendingRegistrations']);
    Route::post('/pending-registrations/{id}/approve', [AuthController::class, 'approveRegistration']);
    Route::post('/pending-registrations/{id}/reject', [AuthController::class, 'rejectRegistration']);
});

// Notification routes (Authenticated users)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});

// Report routes
Route::post('/reports', [ReportController::class, 'store']); // Public - anyone can submit a report

// Admin-only report management routes
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/{id}', [ReportController::class, 'show']);
    Route::put('/reports/{id}/status', [ReportController::class, 'updateStatus']);
});

// Audit Log routes (Admin only)
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index']);
    Route::get('/audit-logs/stats', [\App\Http\Controllers\AuditLogController::class, 'stats']);
});

// Analytics routes (Admin only)
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/analytics/extractions', [AnalyticsController::class, 'extractionVolume']);
    Route::get('/analytics/verifications', [AnalyticsController::class, 'verificationRates']);
    Route::get('/analytics/quarries', [AnalyticsController::class, 'extractionByQuarry']);
    Route::get('/analytics/export', [AnalyticsController::class, 'export']);
});
