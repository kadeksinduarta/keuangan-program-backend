<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Get all users
     */
    public function index(Request $request)
    {
        $users = User::select('id', 'name', 'email', 'role', 'full_name', 'phone')->get();
        return response()->json(['users' => $users]);
    }

    /**
     * Get a specific user
     */
    public function show(Request $request, $id)
    {
        $user = User::findOrFail($id);
        return response()->json(['user' => $user]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $authUser = $request->user();

        // Users can only update their own profile
        if ($authUser->id != $id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'full_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|string',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $authUser = $request->user();

        // Users can only update their own password
        if ($authUser->id != $id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 400);
        }

        $user->update([
            'password' => Hash::make($validated['password'])
        ]);

        return response()->json(['message' => 'Password updated successfully']);
    }

    /**
     * Get user notifications
     */
    public function notifications(Request $request)
    {
        $user = $request->user();
        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['notifications' => $notifications]);
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);
        $user = $request->user();

        // Users can only mark their own notifications
        if ($notification->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read']);
    }
}
