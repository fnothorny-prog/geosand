<?php

namespace App\Http\Controllers;

use App\Models\Extraction;
use App\Models\Notification;
use App\Models\Quarry;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ExtractionController extends Controller
{
    use ApiResponse;

    /**
     * List extractions with role-based filtering.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Extraction::with(['quarry', 'operator']);

        // Role-based filtering
        if ($user->role === 'operator') {
            $query->where('operator_id', $user->id);
        }

        // Optional status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Optional quarry filter
        if ($request->has('quarry_id')) {
            $query->where('quarry_id', $request->quarry_id);
        }

        // Optional date range filter
        if ($request->has('date_from')) {
            $query->whereDate('extraction_timestamp', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('extraction_timestamp', '<=', $request->date_to);
        }

        $extractions = $query->orderBy('extraction_timestamp', 'desc')->paginate(20);

        // Attach verification data to each extraction
        $extractions->getCollection()->transform(function ($extraction) {
            $verification = \DB::table('verifications')
                ->where('extraction_record_id', $extraction->id)
                ->orderBy('verified_at', 'desc')
                ->first();
            $extraction->verification = $verification;
            return $extraction;
        });

        return $this->successResponse([
            'extractions' => $extractions,
        ]);
    }

    /**
     * Submit a new extraction (Operator only).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'quarry_id' => 'required|exists:quarries,id',
            'truck_identifier' => 'required|string|max:255',
            'reported_quantity' => 'required|numeric|min:0',
            'destination' => 'required|string|max:255',
        ]);

        $quarry = Quarry::find($request->quarry_id);
        if ($quarry->status !== 'active') {
            return $this->errorResponse('Cannot submit extraction for inactive quarry', 422);
        }

        $extraction = Extraction::create([
            'quarry_id' => $request->quarry_id,
            'operator_id' => $request->user()->id,
            'truck_identifier' => strtoupper($request->truck_identifier),
            'reported_quantity' => $request->reported_quantity,
            'destination' => $request->destination,
            'extraction_timestamp' => now(),
            'status' => 'pending',
        ]);

        return $this->successResponse([
            'extraction' => $extraction->load(['quarry', 'operator']),
        ], 'Extraction submitted successfully', 201);
    }

    /**
     * Get extraction details.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $extraction = Extraction::with(['quarry', 'operator'])->find($id);

        if (!$extraction) {
            return $this->errorResponse('Extraction not found', 404);
        }

        $user = $request->user();
        if ($user->role === 'operator' && $extraction->operator_id !== $user->id) {
            return $this->errorResponse('Unauthorized action', 403);
        }

        // Attach verification data
        $extraction->verification = \DB::table('verifications')
            ->join('users', 'verifications.checkpoint_user_id', '=', 'users.id')
            ->select('verifications.*', 'users.name as checkpoint_name')
            ->where('extraction_record_id', $extraction->id)
            ->orderBy('verified_at', 'desc')
            ->first();

        return $this->successResponse([
            'extraction' => $extraction,
        ]);
    }

    /**
     * Verify an extraction (Checkpoint only).
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(Request $request, $id)
    {
        $request->validate([
            'actual_quantity' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $extraction = Extraction::find($id);

        if (!$extraction) {
            return $this->errorResponse('Extraction not found', 404);
        }

        if ($extraction->status !== 'pending') {
            return $this->errorResponse('Only pending extractions can be verified', 422);
        }

        // Calculate variance
        $variance = 0;
        if ($extraction->reported_quantity > 0) {
            $variance = abs($request->actual_quantity - $extraction->reported_quantity) / $extraction->reported_quantity * 100;
        }

        // Create verification record
        \DB::table('verifications')->insert([
            'extraction_record_id' => $extraction->id,
            'checkpoint_user_id' => $request->user()->id,
            'actual_quantity' => $request->actual_quantity,
            'variance_percentage' => round($variance, 2),
            'notes' => $request->notes,
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update extraction status
        $newStatus = $variance > 10 ? 'discrepancy' : 'verified';
        $extraction->update(['status' => $newStatus]);

        return $this->successResponse([
            'extraction' => $extraction->load(['quarry', 'operator']),
        ], 'Extraction verified successfully');
    }

    public function reject(Request $request, $id)
    {
        $extraction = Extraction::find($id);

        if (!$extraction) {
            return $this->errorResponse('Extraction not found', 404);
        }

        if ($extraction->status !== 'pending') {
            return $this->errorResponse('Only pending extractions can be rejected', 422);
        }

        $extraction->update(['status' => 'discrepancy']);

        return $this->successResponse([
            'extraction' => $extraction->load(['quarry', 'operator']),
        ], 'Extraction rejected successfully');
    }

    /**
     * List pending extractions (for Checkpoint users).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pending(Request $request)
    {
        $extractions = Extraction::with(['quarry', 'operator'])
            ->where('status', 'pending')
            ->orderBy('extraction_timestamp', 'asc')
            ->paginate(20);

        return $this->successResponse([
            'extractions' => $extractions,
        ]);
    }

    /**
     * Get monitoring stats (for checkpoint/admin).
     */
    public function stats(Request $request)
    {
        $today = now()->toDateString();

        $total      = Extraction::count();
        $pending    = Extraction::where('status', 'pending')->count();
        $verified   = Extraction::where('status', 'verified')->count();
        $discrepancy = Extraction::where('status', 'discrepancy')->count();
        $todayTotal = Extraction::whereDate('extraction_timestamp', $today)->count();
        $todayVerified = \DB::table('verifications')->whereDate('verified_at', $today)->count();

        $avgVariance = \DB::table('verifications')->avg('variance_percentage');

        return $this->successResponse([
            'stats' => [
                'total'          => $total,
                'pending'        => $pending,
                'verified'       => $verified,
                'discrepancy'    => $discrepancy,
                'today_total'    => $todayTotal,
                'today_verified' => $todayVerified,
                'avg_variance'   => round($avgVariance ?? 0, 2),
            ],
        ]);
    }
}
