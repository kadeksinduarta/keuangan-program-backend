<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Get all transactions for a program
     */
    public function index(Request $request, int $programId)
    {
        $program = Program::findOrFail($programId);
        
        $query = Transaction::where('program_id', $programId)
            ->with(['creator', 'rabAllocations.rabItem', 'receipts']);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        // Filter by RAB item
        if ($request->has('rab_item_id')) {
            $query->whereHas('rabAllocations', function ($q) use ($request) {
                $q->where('rab_item_id', $request->rab_item_id);
            });
        }

        $transactions = $query->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    /**
     * Create new transaction
     */
    public function store(Request $request, int $programId)
    {
        $program = Program::findOrFail($programId);

        // Validasi program bisa menerima transaksi
        if (!$program->canAddTransaction()) {
            return response()->json([
                'success' => false,
                'message' => 'Program belum aktif atau belum memiliki RAB. Tidak bisa menambah transaksi.'
            ], 403);
        }

        $request->validate([
            'type' => 'required|in:income,expense',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string',
            'rab_allocations' => 'required_if:type,expense|array',
            'rab_allocations.*.rab_item_id' => 'required_if:type,expense|exists:rab_items,id',
            'rab_allocations.*.amount' => 'required_if:type,expense|numeric|min:0.01',
        ]);

        try {
            if ($request->type === 'income') {
                $transaction = $this->transactionService->createIncome($program, $request->all());
            } else {
                $transaction = $this->transactionService->createExpense($program, $request->all());
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dibuat',
                'data' => $transaction->load(['creator', 'rabAllocations.rabItem', 'receipts'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get single transaction
     */
    public function show(int $id)
    {
        $transaction = Transaction::with(['program', 'creator', 'rabAllocations.rabItem', 'receipts'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $transaction
        ]);
    }

    /**
     * Update transaction
     */
    public function update(Request $request, int $id)
    {
        $transaction = Transaction::findOrFail($id);
        $program = $transaction->program;

        // Validasi program bisa menerima transaksi
        if (!$program->canAddTransaction()) {
            return response()->json([
                'success' => false,
                'message' => 'Program belum aktif atau belum memiliki RAB.'
            ], 403);
        }

        $request->validate([
            'date' => 'sometimes|required|date',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'description' => 'sometimes|required|string',
            'rab_allocations' => 'required_if:type,expense|array',
            'rab_allocations.*.rab_item_id' => 'required_if:type,expense|exists:rab_items,id',
            'rab_allocations.*.amount' => 'required_if:type,expense|numeric|min:0.01',
        ]);

        try {
            $transaction = $this->transactionService->update($transaction, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil diupdate',
                'data' => $transaction->load(['creator', 'rabAllocations.rabItem', 'receipts'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Delete transaction
     */
    public function destroy(int $id)
    {
        $transaction = Transaction::findOrFail($id);

        try {
            $this->transactionService->delete($transaction);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}

