<?php

namespace App\Http\Controllers;

use App\Models\Quarry;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class QuarryController extends Controller
{
    use ApiResponse;

    // Bongabong, Oriental Mindoro approximate boundaries
    // These coordinates define a bounding box for Bongabong
    private const BONGABONG_MIN_LAT = 12.5000;
    private const BONGABONG_MAX_LAT = 12.9500;
    private const BONGABONG_MIN_LNG = 121.2000;
    private const BONGABONG_MAX_LNG = 121.6000;

    /**
     * List all quarries (public endpoint - active quarries only for non-admin).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Quarry::query();

        // If user is not authenticated or not an admin, show only active quarries
        if (!$request->user() || $request->user()->role !== 'admin') {
            $query->where('status', 'active');
        }

        $quarries = $query->get();

        return $this->successResponse([
            'quarries' => $quarries,
        ]);
    }

    /**
     * Get a specific quarry (public endpoint).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $quarry = Quarry::find($id);

        if (!$quarry) {
            return $this->errorResponse('Quarry not found', 404);
        }

        return $this->successResponse([
            'quarry' => $quarry,
        ]);
    }

    /**
     * Create a new quarry (Admin only).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'permit_number' => 'required|string|max:255|unique:quarries,permit_number',
            'address' => 'required|string|max:500',
            'permit_expiry' => 'required|date|after_or_equal:today',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'area' => 'nullable|numeric|min:0',
            'polygon' => 'nullable|json',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Validate coordinates are within Bongabong, Oriental Mindoro boundaries
        if (!$this->isWithinBongabongBoundaries($validated['latitude'], $validated['longitude'])) {
            return $this->errorResponse(
                'Coordinates must be within Bongabong, Oriental Mindoro boundaries',
                422,
                [
                    'latitude' => ['Coordinates must be within Bongabong, Oriental Mindoro boundaries'],
                    'longitude' => ['Coordinates must be within Bongabong, Oriental Mindoro boundaries'],
                ]
            );
        }

        $quarry = Quarry::create([
            'name' => $validated['name'],
            'permit_number' => $validated['permit_number'],
            'location' => $validated['address'],
            'permit_expiry_date' => $validated['permit_expiry'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'total_area_hectares' => $validated['area'] ?? null,
            'polygon' => $validated['polygon'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => ($validated['is_active'] ?? true) ? 'active' : 'inactive',
        ]);

        // Log audit
        \App\Models\AuditLog::log(
            'create',
            "Created quarry: {$quarry->name} (Permit: {$quarry->permit_number})",
            'Quarry',
            $quarry->id,
            null,
            $quarry->only(['name', 'permit_number', 'address', 'permit_expiry', 'latitude', 'longitude', 'area', 'is_active'])
        );

        return $this->successResponse([
            'quarry' => $quarry->load('creator:id,username'),
        ], 'Quarry created successfully', 201);
    }

    /**
     * Update a quarry (Admin only).
     * Preserves historical data by not modifying extraction relationships.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $quarry = Quarry::find($id);

        if (!$quarry) {
            return $this->errorResponse('Quarry not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'permit_number' => 'sometimes|required|string|max:255|unique:quarries,permit_number,' . $id,
            'address' => 'sometimes|required|string|max:500',
            'permit_expiry' => 'sometimes|required|date',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'area' => 'nullable|numeric|min:0',
            'polygon' => 'nullable|json',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // If coordinates are being updated, validate they're within boundaries
        $latitude = $validated['latitude'] ?? $quarry->latitude;
        $longitude = $validated['longitude'] ?? $quarry->longitude;

        if (!$this->isWithinBongabongBoundaries($latitude, $longitude)) {
            return $this->errorResponse(
                'Coordinates must be within Bongabong, Oriental Mindoro boundaries',
                422,
                [
                    'latitude' => ['Coordinates must be within Bongabong, Oriental Mindoro boundaries'],
                    'longitude' => ['Coordinates must be within Bongabong, Oriental Mindoro boundaries'],
                ]
            );
        }

        // Store old values for audit
        $oldValues = $quarry->only(['name', 'permit_number', 'location', 'permit_expiry_date', 'latitude', 'longitude', 'total_area_hectares', 'status']);

        // Map frontend field names to database field names
        $updateData = [];
        if (isset($validated['name'])) $updateData['name'] = $validated['name'];
        if (isset($validated['permit_number'])) $updateData['permit_number'] = $validated['permit_number'];
        if (isset($validated['address'])) $updateData['location'] = $validated['address'];
        if (isset($validated['permit_expiry'])) $updateData['permit_expiry_date'] = $validated['permit_expiry'];
        if (isset($validated['latitude'])) $updateData['latitude'] = $validated['latitude'];
        if (isset($validated['longitude'])) $updateData['longitude'] = $validated['longitude'];
        if (isset($validated['area'])) $updateData['total_area_hectares'] = $validated['area'];
        if (isset($validated['polygon'])) $updateData['polygon'] = $validated['polygon'];
        if (isset($validated['description'])) $updateData['description'] = $validated['description'];
        if (isset($validated['is_active'])) $updateData['status'] = $validated['is_active'] ? 'active' : 'inactive';

        // Update only the provided fields
        $quarry->update($updateData);

        // Log audit
        \App\Models\AuditLog::log(
            'update',
            "Updated quarry: {$quarry->name}",
            'Quarry',
            $quarry->id,
            $oldValues,
            $quarry->only(['name', 'permit_number', 'address', 'permit_expiry', 'latitude', 'longitude', 'area', 'is_active'])
        );

        return $this->successResponse([
            'quarry' => $quarry->load('creator:id,username'),
        ], 'Quarry updated successfully');
    }

    /**
     * Deactivate a quarry (Admin only).
     * Sets is_active to false, preserving all historical data.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $quarry = Quarry::find($id);

        if (!$quarry) {
            return $this->errorResponse('Quarry not found', 404);
        }

        // Soft deactivation - set status to inactive
        $quarry->update(['status' => 'inactive']);

        // Log audit
        \App\Models\AuditLog::log(
            'delete',
            "Deactivated quarry: {$quarry->name}",
            'Quarry',
            $quarry->id,
            ['status' => 'active'],
            ['status' => 'inactive']
        );

        return $this->successResponse([
            'quarry' => $quarry,
        ], 'Quarry deactivated successfully');
    }

    /**
     * Check if coordinates are within Bongabong, Oriental Mindoro boundaries.
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
