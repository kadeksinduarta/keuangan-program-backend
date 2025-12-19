<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Expense;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Get expense report with filters
     */
    public function expenses(Request $request, $programId)
    {
        $program = Program::findOrFail($programId);
        $user = $request->user();

        // Check access
        if ($program->admin_id !== $user->id && !$program->members->contains($user->id)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $query = Expense::where('program_id', $programId)
            ->with(['category', 'submittedBy', 'approvedBy', 'receipts']);

        // Apply filters
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->end_date);
        }

        $expenses = $query->orderBy('transaction_date', 'desc')->get();

        $summary = [
            'total_expenses' => $expenses->count(),
            'total_amount' => $expenses->where('status', 'approved')->sum('amount'),
            'pending_amount' => $expenses->where('status', 'pending')->sum('amount'),
        ];

        return response()->json([
            'expenses' => $expenses,
            'summary' => $summary
        ]);
    }

    /**
     * Export report to PDF (placeholder - requires dompdf package)
     */
    public function exportPdf(Request $request, $programId)
    {
        // This would require barryvdh/laravel-dompdf package
        // For now, return a message
        return response()->json([
            'message' => 'PDF export functionality - requires barryvdh/laravel-dompdf package to be installed',
            'install_command' => 'composer require barryvdh/laravel-dompdf'
        ]);
    }

    /**
     * Export report to Excel (placeholder - requires maatwebsite/excel package)
     */
    public function exportExcel(Request $request, $programId)
    {
        // This would require maatwebsite/excel package
        // For now, return a message
        return response()->json([
            'message' => 'Excel export functionality - requires maatwebsite/excel package to be installed',
            'install_command' => 'composer require maatwebsite/excel'
        ]);
    }
}
