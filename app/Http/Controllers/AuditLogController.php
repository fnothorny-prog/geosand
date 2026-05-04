<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponse;

    /**
     * Get audit logs (admin only).
     */
    public function index(Request $request)
    {
        $query = AuditLog::with('user:id,name,role')
            ->orderBy('created_at', 'desc');

        // Filter by action
        if ($request->has('action') && $request->action !== 'all') {
            $query->where('action', $request->action);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by model type
        if ($request->has('model_type') && $request->model_type !== 'all') {
            $query->where('model_type', $request->model_type);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Get per_page from request, default to 10, max 100
        $perPage = min($request->get('per_page', 10), 100);
        $logs = $query->paginate($perPage);

        return $this->successResponse([
            'logs' => $logs,
        ]);
    }

    /**
     * Get audit log statistics.
     */
    public function stats()
    {
        $stats = [
            'total_logs' => AuditLog::count(),
            'today_logs' => AuditLog::whereDate('created_at', today())->count(),
            'by_action' => AuditLog::selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->pluck('count', 'action'),
            'by_model' => AuditLog::selectRaw('model_type, COUNT(*) as count')
                ->whereNotNull('model_type')
                ->groupBy('model_type')
                ->pluck('count', 'model_type'),
        ];

        return $this->successResponse($stats);
    }
}
