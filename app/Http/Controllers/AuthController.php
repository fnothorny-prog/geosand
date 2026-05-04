<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Handle user login with Sanctum token generation.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        \Log::info('Login attempt', ['email' => $request->email]);
        
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            \Log::warning('Login failed: User not found', ['email' => $request->email]);
            return $this->unauthorizedResponse('Invalid credentials');
        }

        if (!Hash::check($request->password, $user->password)) {
            \Log::warning('Login failed: Invalid password', ['email' => $request->email]);
            return $this->unauthorizedResponse('Invalid credentials');
        }

        if (!$user->is_active) {
            \Log::warning('Login failed: Account deactivated', ['email' => $request->email]);
            return $this->unauthorizedResponse('Account is deactivated');
        }

        // Create token with 24-hour expiration
        $token = $user->createToken('auth-token', ['*'], now()->addHours(24))->plainTextToken;

        \Log::info('Login successful', ['email' => $request->email, 'user_id' => $user->id]);

        // Log audit
        \App\Models\AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'resource_type' => 'User',
            'resource_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return $this->successResponse([
            'user' => $user,
            'token' => $token,
        ], 'Login successful');
    }

    /**
     * Handle user logout with token invalidation.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        
        // Log audit
        \App\Models\AuditLog::create([
            'user_id' => $user->id,
            'action' => 'logout',
            'resource_type' => 'User',
            'resource_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);
        
        // Delete the current access token
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logout successful');
    }

    /**
     * Get authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        return $this->successResponse([
            'user' => $request->user(),
        ]);
    }

    /**
     * Register a new user (Admin only).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['operator', 'checkpoint', 'admin'])],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password, // Will be hashed automatically by the model
            'role' => $request->role,
            'is_active' => true,
        ]);

        return $this->successResponse([
            'user' => $user,
        ], 'User registered successfully', 201);
    }

    /**
     * Public operator self-registration (pending admin approval).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function operatorRegister(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|max:255|unique:users',
            'password'      => 'required|string|min:8|confirmed',
            'company_name'  => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
        ]);

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'role'      => 'operator',
            'is_active' => false, // Pending admin approval
        ]);

        return $this->successResponse([
            'user' => $user,
        ], 'Registration submitted successfully. Please wait for admin approval.', 201);
    }

    /**
     * Get all pending operator registrations (Admin only).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pendingRegistrations()
    {
        $pending = User::where('role', 'operator')
            ->where('is_active', false)
            ->whereNull('quarry_id')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse([
            'registrations' => $pending,
        ]);
    }

    /**
     * Approve a pending operator registration (Admin only).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveRegistration($id)
    {
        $user = User::where('id', $id)
            ->where('role', 'operator')
            ->where('is_active', false)
            ->first();

        if (!$user) {
            return $this->errorResponse('Pending registration not found', 404);
        }

        $user->is_active = true;
        $user->save();

        return $this->successResponse([
            'user' => $user,
        ], 'Registration approved successfully.');
    }

    /**
     * Reject a pending operator registration (Admin only).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function rejectRegistration($id)
    {
        $user = User::where('id', $id)
            ->where('role', 'operator')
            ->where('is_active', false)
            ->first();

        if (!$user) {
            return $this->errorResponse('Pending registration not found', 404);
        }

        $user->delete();

        return $this->successResponse(null, 'Registration rejected and removed.');
    }
}
