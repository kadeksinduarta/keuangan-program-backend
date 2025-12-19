<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Expense;
use App\Models\Receipt;
use App\Models\RabCategory;
use App\Models\ActivityLog;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    /**
     * Display expenses for a program
     */
    public function index(Request $request, $programId)
    {
        $program = Program::findOrFail($programId);
        $user = $request->user();

        // Check access
        if ($program->created_by !== $user->id && !$program->members->contains($user->id)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $query = Expense::where('program_id', $programId)
            ->with(['category', 'submittedBy', 'approvedBy', 'receipts'])
            ->orderBy('created_at', 'desc');

        // Filter by user if not admin/creator (assuming 'admin' role check is needed or based on program ownership)
        // If the user model has a 'role' attribute, check it. Or check if user is program creator.
        // Based on frontend logic: if (user?.role !== 'admin' && e.submitted_by?.id !== user?.id)
        if ($user->role !== 'admin' && $program->created_by !== $user->id) {
             $query->where('submitted_by', $user->id);
        }

        $expenses = $query->paginate(15);

        return response()->json(['expenses' => $expenses]);
    }

    /**
     * Store a new expense
     */
    public function store(Request $request, $programId)
    {
        $program = Program::findOrFail($programId);
        $user = $request->user();

        // Check access (must be member or admin)
        if ($program->created_by !== $user->id && !$program->members->contains($user->id)) {
            return response()->json(['message' => 'Unauthorized. Not a member of this program.'], 403);
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:rab_categories,id',
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string',
            'transaction_date' => 'required|date',
            'receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048', // 2MB max
        ]);

        // Verify category belongs to this program
        $category = RabCategory::findOrFail($validated['category_id']);
        if ($category->program_id != $programId) {
            return response()->json(['message' => 'Invalid category for this program'], 400);
        }

        // Check if expense exceeds remaining budget
        $remainingBudget = $category->allocated_budget - $category->spent_amount;
        if ($validated['amount'] > $remainingBudget) {
            return response()->json([
                'message' => 'Expense amount exceeds remaining budget for this category',
                'remaining_budget' => $remainingBudget,
                'requested_amount' => $validated['amount']
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Create expense
            $expense = Expense::create([
                'program_id' => $programId,
                'category_id' => $validated['category_id'],
                'submitted_by' => $user->id,
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'transaction_date' => $validated['transaction_date'],
                'status' => 'pending',
            ]);

            // Upload receipt
            if ($request->hasFile('receipt')) {
                $file = $request->file('receipt');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs("receipts/{$programId}/{$expense->id}", $filename, 'public');

                Receipt::create([
                    'expense_id' => $expense->id,
                    'file_name' => $filename,
                    'file_path' => $path,
                    'file_type' => $file->getClientOriginalExtension(),
                    'file_size' => $file->getSize(),
                ]);
            }

            // Log activity
            ActivityLog::create([
                'program_id' => $programId,
                'user_id' => $user->id,
                'action' => 'expense_submitted',
                'description' => "Expense submitted: {$expense->description} - " . number_format($expense->amount, 2),
                'metadata' => ['expense_id' => $expense->id],
            ]);

            // Notify admin
            Notification::create([
                'user_id' => $program->created_by,
                'type' => 'expense_submitted',
                'title' => 'New Expense Submitted',
                'message' => "{$user->name} submitted an expense of " . number_format($expense->amount, 2) . " for approval",
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Expense submitted successfully',
                'expense' => $expense->load(['category', 'submittedBy', 'receipts'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Failed to submit expense: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display a specific expense
     */
    public function show(Request $request, $id)
    {
        $expense = Expense::with(['program', 'category', 'submittedBy', 'approvedBy', 'receipts'])
            ->findOrFail($id);
        $user = $request->user();

        // Check access
        $program = $expense->program;
        if ($program->created_by !== $user->id && !$program->members->contains($user->id)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        return response()->json(['expense' => $expense]);
    }

    /**
     * Update an expense (only if pending and by submitter)
     */
    public function update(Request $request, $id)
    {
        $expense = Expense::with('program')->findOrFail($id);
        $user = $request->user();

        // Only submitter can update
        if ($expense->submitted_by !== $user->id) {
            return response()->json(['message' => 'Unauthorized. You can only edit your own expenses.'], 403);
        }

        // Only pending expenses can be updated
        if ($expense->status !== 'pending') {
            return response()->json(['message' => 'Cannot update expense that has been processed'], 400);
        }

        $validated = $request->validate([
            'category_id' => 'sometimes|exists:rab_categories,id',
            'amount' => 'sometimes|numeric|min:0',
            'description' => 'sometimes|string',
            'transaction_date' => 'sometimes|date',
        ]);

        // If category is being changed, verify it belongs to the program
        if (isset($validated['category_id'])) {
            $category = RabCategory::findOrFail($validated['category_id']);
            if ($category->program_id != $expense->program_id) {
                return response()->json(['message' => 'Invalid category for this program'], 400);
            }
            
            // Check budget
            $remainingBudget = $category->allocated_budget - $category->spent_amount;
            $amount = $validated['amount'] ?? $expense->amount;
            if ($amount > $remainingBudget) {
                return response()->json([
                    'message' => 'Expense amount exceeds remaining budget',
                    'remaining_budget' => $remainingBudget
                ], 400);
            }
        }

        $expense->update($validated);

        return response()->json([
            'message' => 'Expense updated successfully',
            'expense' => $expense->load(['category', 'submittedBy', 'receipts'])
        ]);
    }

    /**
     * Delete an expense (only if pending and by submitter)
     */
    public function destroy(Request $request, $id)
    {
        $expense = Expense::with('receipts')->findOrFail($id);
        $user = $request->user();

        // Only submitter can delete
        if ($expense->submitted_by !== $user->id) {
            return response()->json(['message' => 'Unauthorized. You can only delete your own expenses.'], 403);
        }

        // Only pending expenses can be deleted
        if ($expense->status !== 'pending') {
            return response()->json(['message' => 'Cannot delete expense that has been processed'], 400);
        }

        // Delete associated files
        foreach ($expense->receipts as $receipt) {
            Storage::disk('public')->delete($receipt->file_path);
        }

        $expense->delete();

        return response()->json(['message' => 'Expense deleted successfully']);
    }

    /**
     * Approve an expense (admin only)
     */
    public function approve(Request $request, $id)
    {
        $expense = Expense::with(['program', 'category', 'submittedBy'])->findOrFail($id);
        $user = $request->user();

        // Only program admin can approve
        if ($expense->program->admin_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized. Only program admin can approve expenses.'], 403);
        }

        // Only pending expenses can be approved
        if ($expense->status !== 'pending') {
            return response()->json(['message' => 'Expense is not pending'], 400);
        }

        // Check budget one more time
        $category = $expense->category;
        $remainingBudget = $category->allocated_budget - $category->spent_amount;
        if ($expense->amount > $remainingBudget) {
            return response()->json([
                'message' => 'Cannot approve. Expense exceeds remaining budget.',
                'remaining_budget' => $remainingBudget
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Update expense status
            $expense->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            // Update category spent amount
            $category->increment('spent_amount', $expense->amount);

            // Log activity
            ActivityLog::create([
                'program_id' => $expense->program_id,
                'user_id' => $user->id,
                'action' => 'expense_approved',
                'description' => "Expense approved: {$expense->description} - " . number_format($expense->amount, 2),
                'metadata' => ['expense_id' => $expense->id],
            ]);

            // Notify submitter
            Notification::create([
                'user_id' => $expense->submitted_by,
                'type' => 'expense_approved',
                'title' => 'Expense Approved',
                'message' => "Your expense of " . number_format($expense->amount, 2) . " has been approved",
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Expense approved successfully',
                'expense' => $expense->fresh()->load(['category', 'submittedBy', 'approvedBy', 'receipts'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Failed to approve expense: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reject an expense (admin only)
     */
    public function reject(Request $request, $id)
    {
        $expense = Expense::with(['program', 'submittedBy'])->findOrFail($id);
        $user = $request->user();

        // Only program admin can reject
        if ($expense->program->admin_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized. Only program admin can reject expenses.'], 403);
        }

        // Only pending expenses can be rejected
        if ($expense->status !== 'pending') {
            return response()->json(['message' => 'Expense is not pending'], 400);
        }

        $validated = $request->validate([
            'rejection_note' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            // Update expense status
            $expense->update([
                'status' => 'rejected',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejection_note' => $validated['rejection_note'],
            ]);

            // Log activity
            ActivityLog::create([
                'program_id' => $expense->program_id,
                'user_id' => $user->id,
                'action' => 'expense_rejected',
                'description' => "Expense rejected: {$expense->description}",
                'metadata' => ['expense_id' => $expense->id, 'reason' => $validated['rejection_note']],
            ]);

            // Notify submitter
            Notification::create([
                'user_id' => $expense->submitted_by,
                'type' => 'expense_rejected',
                'title' => 'Expense Rejected',
                'message' => "Your expense was rejected. Reason: {$validated['rejection_note']}",
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Expense rejected',
                'expense' => $expense->fresh()->load(['category', 'submittedBy', 'approvedBy', 'receipts'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Failed to reject expense: ' . $e->getMessage()], 500);
        }
    }
}
