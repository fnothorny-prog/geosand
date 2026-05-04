<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * List all users with optional role filtering.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = User::query()->with('quarry');

        // Filter by role if provided
        if ($request->has('role')) {
            $request->validate([
                'role' => ['string', Rule::in(['operator', 'checkpoint', 'admin'])],
            ]);
            $query->where('role', $request->role);
        }

        // Filter by active status if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        return $this->successResponse([
            'users' => $users,
        ]);
    }

    /**
     * Create a new user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['operator', 'checkpoint', 'admin'])],
        ];

        // Add quarry_id validation for operators
        if ($request->role === 'operator') {
            $rules['quarry_id'] = [
                'required',
                'exists:quarries,id',
                // Ensure quarry doesn't already have an operator
                Rule::unique('users', 'quarry_id'),
            ];
        }

        $request->validate($rules);

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password, // Will be hashed automatically by the model
            'role' => $request->role,
            'is_active' => true,
        ];

        // Add quarry_id for operators
        if ($request->role === 'operator' && $request->has('quarry_id')) {
            $userData['quarry_id'] = $request->quarry_id;
        }

        $user = User::create($userData);

        // Log audit
        \App\Models\AuditLog::log(
            'create',
            "Created user: {$user->name} ({$user->role})",
            'User',
            $user->id,
            null,
            $user->only(['name', 'email', 'role', 'is_active'])
        );

        return $this->successResponse([
            'user' => $user,
        ], 'User created successfully', 201);
    }

    /**
     * Get user details.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->notFoundResponse('User not found');
        }

        return $this->successResponse([
            'user' => $user,
        ]);
    }

    /**
     * Update user information.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->notFoundResponse('User not found');
        }

        $rules = [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|string|min:8',
            'role' => ['sometimes', Rule::in(['operator', 'checkpoint', 'admin'])],
            'is_active' => 'sometimes|boolean',
        ];

        // Add quarry_id validation for operators
        $newRole = $request->has('role') ? $request->role : $user->role;
        if ($newRole === 'operator') {
            $rules['quarry_id'] = [
                'sometimes',
                'nullable',
                'exists:quarries,id',
                // Ensure quarry doesn't already have another operator
                Rule::unique('users', 'quarry_id')->ignore($user->id),
            ];
        }

        $request->validate($rules);

        // Store old values for audit
        $oldValues = $user->only(['name', 'email', 'role', 'is_active', 'quarry_id']);

        // Update only provided fields
        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('password')) {
            $user->password = $request->password; // Will be hashed automatically
        }
        if ($request->has('role')) {
            $user->role = $request->role;
            // Clear quarry_id if role changes from operator
            if ($request->role !== 'operator') {
                $user->quarry_id = null;
            }
        }
        if ($request->has('quarry_id')) {
            $user->quarry_id = $request->quarry_id;
        }
        if ($request->has('is_active')) {
            $user->is_active = $request->is_active;
        }

        $user->save();

        // Log audit
        \App\Models\AuditLog::log(
            'update',
            "Updated user: {$user->name} ({$user->email})",
            'User',
            $user->id,
            $oldValues,
            $user->only(['name', 'email', 'role', 'is_active', 'quarry_id'])
        );

        return $this->successResponse([
            'user' => $user,
        ], 'User updated successfully');
    }

    /**
     * Deactivate a user (soft delete).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->notFoundResponse('User not found');
        }

        // Deactivate user instead of deleting
        $user->is_active = false;
        $user->save();

        // Log audit
        \App\Models\AuditLog::log(
            'delete',
            "Deactivated user: {$user->name}",
            'User',
            $user->id,
            ['is_active' => true],
            ['is_active' => false]
        );

        return $this->successResponse([
            'user' => $user,
        ], 'User deactivated successfully');
    }
}
