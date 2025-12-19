<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Program;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Get audit logs for a program
     */
    public function index(Request $request, int $programId)
    {
        $program = Program::findOrFail($programId);

        $query = AuditLog::where(function ($q) use ($programId) {
            // Logs untuk program
            $q->where('module', 'PROGRAM')
              ->where('module_id', $programId);
        })
        ->orWhere(function ($q) use ($programId) {
            // Logs untuk RAB items program ini
            $q->where('module', 'RAB_ITEM')
              ->whereIn('module_id', function ($subQuery) use ($programId) {
                  $subQuery->select('id')
                      ->from('rab_items')
                      ->where('program_id', $programId);
              });
        })
        ->orWhere(function ($q) use ($programId) {
            // Logs untuk transaksi program ini
            $q->where('module', 'TRANSACTION')
              ->whereIn('module_id', function ($subQuery) use ($programId) {
                  $subQuery->select('id')
                      ->from('transactions')
                      ->where('program_id', $programId);
              });
        });

        // Filter by module
        if ($request->has('module')) {
            $query->where('module', $request->module);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }
}

