<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\RabCategory;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class RabController extends Controller
{
    /**
     * Display RAB categories for a program
     */
    public function index(Request $request, $programId)
    {
        $program = Program::findOrFail($programId);
        $user = $request->user();

        // Check access
        if ($program->admin_id !== $user->id && !$program->members->contains($user->id)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $rabCategories = RabCategory::where('program_id', $programId)
            ->with('expenses')
            ->get();

        return response()->json(['rab_categories' => $rabCategories]);
    }

    /**
     * Store a new RAB category
     */
    public function store(Request $request, $programId)
    {
        $program = Program::findOrFail($programId);
        $user = $request->user();

        // Only admin can create RAB
        if ($program->admin_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized. Only program admin can create RAB.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'allocated_budget' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $rab = RabCategory::create([
            'program_id' => $programId,
            'name' => $validated['name'],
            'allocated_budget' => $validated['allocated_budget'],
            'description' => $validated['description'] ?? null,
            'spent_amount' => 0,
        ]);

        // Log activity
        ActivityLog::create([
            'program_id' => $programId,
            'user_id' => $user->id,
            'action' => 'rab_created',
            'description' => "RAB category '{$rab->name}' created with budget " . number_format($rab->allocated_budget, 2),
            'metadata' => ['rab_id' => $rab->id],
        ]);

        return response()->json([
            'message' => 'RAB category created successfully',
            'rab_category' => $rab
        ], 201);
    }

    /**
     * Display a specific RAB category
     */
    public function show(Request $request, $id)
    {
        $rab = RabCategory::with('program', 'expenses')->findOrFail($id);
        $user = $request->user();

        // Check access
        if ($rab->program->admin_id !== $user->id && !$rab->program->members->contains($user->id)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        return response()->json(['rab_category' => $rab]);
    }

    /**
     * Update a RAB category
     */
    public function update(Request $request, $id)
    {
        $rab = RabCategory::with('program')->findOrFail($id);
        $user = $request->user();

        // Only admin can update RAB
        if ($rab->program->admin_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized. Only program admin can update RAB.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'allocated_budget' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $rab->update($validated);

        // Log activity
        ActivityLog::create([
            'program_id' => $rab->program_id,
            'user_id' => $user->id,
            'action' => 'rab_updated',
            'description' => "RAB category '{$rab->name}' was updated",
            'metadata' => ['rab_id' => $rab->id],
        ]);

        return response()->json([
            'message' => 'RAB category updated successfully',
            'rab_category' => $rab
        ]);
    }

    /**
     * Delete a RAB category
     */
    public function destroy(Request $request, $id)
    {
        $rab = RabCategory::with('program')->findOrFail($id);
        $user = $request->user();

        // Only admin can delete RAB
        if ($rab->program->admin_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized. Only program admin can delete RAB.'], 403);
        }

        // Check if there are approved expenses
        if ($rab->expenses()->where('status', 'approved')->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete RAB category with approved expenses'
            ], 400);
        }

        $rabName = $rab->name;
        $rab->delete();

        return response()->json(['message' => "RAB category '$rabName' deleted successfully"]);
    }
}
