<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\User;
use App\Models\ProgramUserRole;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgramController extends Controller
{
    /**
     * Display all programs (accessible by user)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            // Public: hanya program yang sudah active
            $programs = Program::where('status', 'active')
                ->with(['creator', 'userRoles.user'])
                ->get();
        } else {
            // Authenticated: semua program yang user punya akses
            $programIds = ProgramUserRole::where('user_id', $user->id)
                ->pluck('program_id')
                ->toArray();
            
            $programs = Program::where('created_by', $user->id)
                ->orWhereIn('id', $programIds)
                ->with(['creator', 'userRoles.user'])
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'programs' => $programs
            ]
        ]);
    }

    /**
     * Store a new program (status: draft)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'period_start' => 'required|date',
            'period_end' => 'nullable|date|after:period_start',
        ]);

        return DB::transaction(function () use ($validated, $user, $request) {
            $program = Program::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'] ?? null,
                'status' => 'draft', // Default: draft
                'created_by' => $user->id,
            ]);

            // Set creator sebagai ketua
            ProgramUserRole::create([
                'program_id' => $program->id,
                'user_id' => $user->id,
                'role' => 'ketua',
            ]);

            // Log audit
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'create',
                'module' => 'PROGRAM',
                'module_id' => $program->id,
                'after_data' => $program->toArray(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Program berhasil dibuat',
                'data' => $program->load(['creator', 'userRoles.user'])
            ], 201);
        });
    }

    /**
     * Display the specified program
     */
    public function show(Request $request, $id)
    {
        $program = Program::with(['creator', 'userRoles.user', 'rabItems', 'transactions'])
            ->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'program' => $program
            ]
        ]);
    }

    /**
     * Update the specified program
     */
    public function update(Request $request, $id)
    {
        $program = Program::findOrFail($id);
        $user = $request->user();

        // Cek akses: harus ketua atau admin
        $userRole = ProgramUserRole::where('program_id', $program->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$userRole || !in_array($userRole->role, ['ketua']) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Hanya ketua program yang bisa mengupdate.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'period_start' => 'sometimes|date',
            'period_end' => 'nullable|date|after:period_start',
        ]);

        $beforeData = $program->toArray();

        $program->update($validated);

        // Log audit
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'update',
            'module' => 'PROGRAM',
            'module_id' => $program->id,
            'before_data' => $beforeData,
            'after_data' => $program->toArray(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Program berhasil diupdate',
            'data' => $program->load(['creator', 'userRoles.user'])
        ]);
    }

    /**
     * Update program status
     */
    public function updateStatus(Request $request, $id)
    {
        $program = Program::findOrFail($id);
        $user = $request->user();

        // Cek akses: harus ketua
        $userRole = ProgramUserRole::where('program_id', $program->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$userRole || $userRole->role !== 'ketua') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Hanya ketua program yang bisa mengubah status.'
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:draft,active,closed,cancelled',
        ]);

        // Validasi: untuk aktifkan program, harus punya RAB
        if ($validated['status'] === 'active' && !$program->hasRab()) {
            return response()->json([
                'success' => false,
                'message' => 'Program harus memiliki RAB sebelum diaktifkan'
            ], 422);
        }

        $beforeData = $program->toArray();
        $program->status = $validated['status'];
        $program->save();

        // Log audit
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'update_status',
            'module' => 'PROGRAM',
            'module_id' => $program->id,
            'before_data' => $beforeData,
            'after_data' => $program->toArray(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status program berhasil diupdate',
            'data' => $program
        ]);
    }

    /**
     * Remove the specified program
     */
    public function destroy(Request $request, $id)
    {
        $program = Program::findOrFail($id);
        $user = $request->user();

        // Cek akses: harus ketua
        $userRole = ProgramUserRole::where('program_id', $program->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$userRole || $userRole->role !== 'ketua') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Hanya ketua program yang bisa menghapus.'
            ], 403);
        }

        $beforeData = $program->toArray();
        $programName = $program->name;
        
        $program->delete();

        // Log audit
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'delete',
            'module' => 'PROGRAM',
            'module_id' => $id,
            'before_data' => $beforeData,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Program '$programName' berhasil dihapus"
        ]);
    }

    /**
     * Add a member to the program
     */
    public function addMember(Request $request, $id)
    {
        $program = Program::findOrFail($id);
        $user = $request->user();

        // Cek akses: harus ketua atau admin
        $isKetua = ProgramUserRole::where('program_id', $program->id)
            ->where('user_id', $user->id)
            ->where('role', 'ketua')
            ->exists();

        if (!$isKetua && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Hanya ketua program yang bisa menambah anggota.'
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:ketua,bendahara,anggota',
        ]);

        // Check if already a member
        $existingRole = ProgramUserRole::where('program_id', $program->id)
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($existingRole) {
            return response()->json([
                'success' => false,
                'message' => 'User sudah menjadi anggota program ini'
            ], 400);
        }


        ProgramUserRole::create([
            'program_id' => $program->id,
            'user_id' => $validated['user_id'],
            'role' => $validated['role'],
            'status' => 'approved',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Anggota berhasil ditambahkan',
            'data' => $program->load(['creator', 'userRoles.user'])
        ]);
    }

    /**
     * Approve invitation by invited user
     */
    public function approveMember(Request $request, $programId)
    {
        $user = $request->user();
        $role = ProgramUserRole::where('program_id', $programId)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();
        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada undangan yang perlu di-approve.'
            ], 404);
        }
        $role->status = 'approved';
        $role->save();
        return response()->json([
            'success' => true,
            'message' => 'Undangan berhasil di-approve.'
        ]);
    }

    /**
     * Remove a member from the program
     */
    public function removeMember(Request $request, $id, $userId)
    {
        $program = Program::findOrFail($id);
        $user = $request->user();

        // Cek akses: harus ketua
        $userRole = ProgramUserRole::where('program_id', $program->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$userRole || !in_array($userRole->role, ['ketua']) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Hanya ketua program yang bisa menghapus anggota.'
            ], 403);
        }

        $memberRole = ProgramUserRole::where('program_id', $program->id)
            ->where('user_id', $userId)
            ->first();

        if (!$memberRole) {
            return response()->json([
                'success' => false,
                'message' => 'User bukan anggota program ini'
            ], 400);
        }

        $memberRole->delete();

        return response()->json([
            'success' => true,
            'message' => 'Anggota berhasil dihapus',
            'data' => $program->load(['creator', 'userRoles.user'])
        ]);
    }

    /**
     * Get all members of a program
     */
    public function getMembers(Request $request, $id)
    {
        $program = Program::findOrFail($id);
        
        $members = ProgramUserRole::where('program_id', $program->id)
            ->with('user')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'members' => $members
            ]
        ]);
    }
}
