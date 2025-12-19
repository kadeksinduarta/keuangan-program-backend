<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\RabItem;
use App\Services\RabService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RabItemController extends Controller
{
    protected RabService $rabService;

    public function __construct(RabService $rabService)
    {
        $this->rabService = $rabService;
    }

    /**
     * Get all RAB items for a program
     */
    public function index(Request $request, int $programId)
    {
        $program = Program::findOrFail($programId);
        
        $rabItems = RabItem::where('program_id', $programId)
            ->with(['transactionAllocations.transaction'])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category' => $item->category,
                    'volume' => $item->volume,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'total_budget' => $item->total_budget,
                    'realized_amount' => $item->realized_amount,
                    'remaining_budget' => $item->remaining_budget,
                    'status' => $item->status,
                    'notes' => $item->notes,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'program' => $program,
                'rab_items' => $rabItems,
                'summary' => [
                    'total_budget' => $rabItems->sum('total_budget'),
                    'total_realized' => $rabItems->sum('realized_amount'),
                    'total_remaining' => $rabItems->sum('remaining_budget'),
                ]
            ]
        ]);
    }

    /**
     * Create new RAB item
     */
    public function store(Request $request, int $programId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'volume' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'unit_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $program = Program::findOrFail($programId);

        // Hanya bisa edit RAB jika program masih draft
        if (!$program->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'RAB hanya bisa diubah saat program masih dalam status DRAFT'
            ], 403);
        }

        $totalBudget = $request->volume * $request->unit_price;

        return DB::transaction(function () use ($request, $programId, $totalBudget) {
            $rabItem = RabItem::create([
                'program_id' => $programId,
                'name' => $request->name,
                'category' => $request->category,
                'volume' => $request->volume,
                'unit' => $request->unit,
                'unit_price' => $request->unit_price,
                'total_budget' => $totalBudget,
                'status' => 'belum_terpenuhi',
                'notes' => $request->notes,
            ]);

            $this->rabService->logAudit('create', $rabItem);

            return response()->json([
                'success' => true,
                'message' => 'RAB item berhasil dibuat',
                'data' => $rabItem
            ], 201);
        });
    }

    /**
     * Get single RAB item
     */
    public function show(int $id)
    {
        $rabItem = RabItem::with(['program', 'transactionAllocations.transaction'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $rabItem->id,
                'name' => $rabItem->name,
                'category' => $rabItem->category,
                'volume' => $rabItem->volume,
                'unit' => $rabItem->unit,
                'unit_price' => $rabItem->unit_price,
                'total_budget' => $rabItem->total_budget,
                'realized_amount' => $rabItem->realized_amount,
                'remaining_budget' => $rabItem->remaining_budget,
                'status' => $rabItem->status,
                'notes' => $rabItem->notes,
                'program' => $rabItem->program,
                'transactions' => $rabItem->transactionAllocations->map(function ($allocation) {
                    return [
                        'transaction_id' => $allocation->transaction_id,
                        'amount' => $allocation->amount,
                        'transaction' => $allocation->transaction,
                    ];
                }),
            ]
        ]);
    }

    /**
     * Update RAB item
     */
    public function update(Request $request, int $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'category' => 'nullable|string|max:255',
            'volume' => 'sometimes|required|numeric|min:0',
            'unit' => 'sometimes|required|string|max:50',
            'unit_price' => 'sometimes|required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $rabItem = RabItem::findOrFail($id);
        $program = $rabItem->program;

        // Hanya bisa edit RAB jika program masih draft
        if (!$program->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'RAB hanya bisa diubah saat program masih dalam status DRAFT'
            ], 403);
        }

        $beforeData = $rabItem->toArray();

        return DB::transaction(function () use ($request, $rabItem, $beforeData) {
            $volume = $request->volume ?? $rabItem->volume;
            $unitPrice = $request->unit_price ?? $rabItem->unit_price;
            $totalBudget = $volume * $unitPrice;

            $rabItem->update([
                'name' => $request->name ?? $rabItem->name,
                'category' => $request->has('category') ? $request->category : $rabItem->category,
                'volume' => $volume,
                'unit' => $request->unit ?? $rabItem->unit,
                'unit_price' => $unitPrice,
                'total_budget' => $totalBudget,
                'notes' => $request->has('notes') ? $request->notes : $rabItem->notes,
            ]);

            $this->rabService->logAudit('update', $rabItem, $beforeData);

            return response()->json([
                'success' => true,
                'message' => 'RAB item berhasil diupdate',
                'data' => $rabItem->fresh()
            ]);
        });
    }

    /**
     * Delete RAB item
     */
    public function destroy(int $id)
    {
        $rabItem = RabItem::findOrFail($id);
        $program = $rabItem->program;

        // Hanya bisa hapus RAB jika program masih draft
        if (!$program->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'RAB hanya bisa dihapus saat program masih dalam status DRAFT'
            ], 403);
        }

        // Cek apakah sudah ada transaksi terkait
        if ($rabItem->transactionAllocations()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa menghapus RAB item yang sudah memiliki transaksi terkait'
            ], 403);
        }

        $beforeData = $rabItem->toArray();

        DB::transaction(function () use ($rabItem, $beforeData) {
            $this->rabService->logAudit('delete', $rabItem, $beforeData);
            $rabItem->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'RAB item berhasil dihapus'
        ]);
    }

    /**
     * Get RAB summary for a program
     */
    public function summary(int $programId)
    {
        $program = Program::findOrFail($programId);
        
        $rabItems = RabItem::where('program_id', $programId)->get();

        $summary = [
            'total_budget' => $rabItems->sum('total_budget'),
            'total_realized' => $rabItems->sum(fn($item) => $item->realized_amount),
            'total_remaining' => $rabItems->sum(fn($item) => $item->remaining_budget),
            'items_by_status' => [
                'belum_terpenuhi' => $rabItems->where('status', 'belum_terpenuhi')->count(),
                'sebagian_terpenuhi' => $rabItems->where('status', 'sebagian_terpenuhi')->count(),
                'terpenuhi' => $rabItems->where('status', 'terpenuhi')->count(),
            ],
            'items' => $rabItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'total_budget' => $item->total_budget,
                    'realized_amount' => $item->realized_amount,
                    'remaining_budget' => $item->remaining_budget,
                    'status' => $item->status,
                    'percentage' => $item->total_budget > 0 
                        ? round(($item->realized_amount / $item->total_budget) * 100, 2) 
                        : 0,
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }
}

