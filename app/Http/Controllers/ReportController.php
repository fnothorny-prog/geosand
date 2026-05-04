<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ApiResponse;

    /**
     * Submit a new report (public endpoint).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reporter_name' => 'nullable|string|max:255',
            'reporter_email' => 'nullable|email|max:255',
            'quarry_id' => 'nullable|exists:quarries,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'description' => 'required|string',
        ]);

        $report = Report::create([
            'reporter_name' => $validated['reporter_name'] ?? null,
            'reporter_email' => $validated['reporter_email'] ?? null,
            'quarry_id' => $validated['quarry_id'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'description' => $validated['description'],
            'status' => 'new',
        ]);

        return $this->successResponse([
            'report' => $report->load('quarry:id,name'),
        ], 'Report submitted successfully', 201);
    }

    /**
     * List all reports (Admin only).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Report::query()->with('quarry:id,name');

        // Optional filtering by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Optional filtering by date
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $reports = $query->orderBy('created_at', 'desc')->get();

        return $this->successResponse([
            'reports' => $reports,
        ]);
    }

    /**
     * Get a specific report (Admin only).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $report = Report::with('quarry:id,name,latitude,longitude')->find($id);

        if (!$report) {
            return $this->errorResponse('Report not found', 404);
        }

        return $this->successResponse([
            'report' => $report,
        ]);
    }

    /**
     * Update report status (Admin only).
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        $report = Report::find($id);

        if (!$report) {
            return $this->errorResponse('Report not found', 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:new,reviewing,resolved,dismissed',
        ]);

        $oldStatus = $report->status;

        $report->update([
            'status' => $validated['status'],
        ]);

        // Log audit
        \App\Models\AuditLog::log(
            'update',
            "Changed report #{$report->id} status from {$oldStatus} to {$validated['status']}",
            'Report',
            $report->id,
            ['status' => $oldStatus],
            ['status' => $validated['status']]
        );

        return $this->successResponse([
            'report' => $report->load('quarry:id,name'),
        ], 'Report status updated successfully');
    }
}
