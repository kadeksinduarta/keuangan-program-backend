<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Get activity logs for a program
     */
    public function index(Request $request, $programId)
    {
        $program = Program::findOrFail($programId);
        $user = $request->user();

        // Check access
        if ($program->admin_id !== $user->id && !$program->members->contains($user->id)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $logs = ActivityLog::where('program_id', $programId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json(['activity_logs' => $logs]);
    }
}
