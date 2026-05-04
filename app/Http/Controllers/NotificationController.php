<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * Get notifications for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Notification::where('user_id', $user->id);

        // Optional filter by read status
        if ($request->has('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->successResponse([
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark a notification as read.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        
        $notification = Notification::where('user_id', $user->id)->find($id);

        if (!$notification) {
            return $this->errorResponse('Notification not found', 404);
        }

        $notification->update(['is_read' => true]);

        return $this->successResponse([
            'notification' => $notification,
        ], 'Notification marked as read');
    }

    /**
     * Mark all notifications as read for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        
        $updatedCount = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return $this->successResponse([
            'updated_count' => $updatedCount,
        ], 'All notifications marked as read');
    }
}
