<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Receipt;
use App\Models\RabItem;
use App\Services\RabService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ReceiptController extends Controller
{
    protected RabService $rabService;

    public function __construct(RabService $rabService)
    {
        $this->rabService = $rabService;
    }

    /**
     * Get all receipts for a transaction
     */
    public function index(int $transactionId)
    {
        $transaction = Transaction::findOrFail($transactionId);
        
        $receipts = $transaction->receipts()->with('uploader')->get();

        return response()->json([
            'success' => true,
            'data' => $receipts
        ]);
    }

    /**
     * Upload receipt for a transaction
     */
    public function store(Request $request, int $transactionId)
    {
        $transaction = Transaction::findOrFail($transactionId);

        // Hanya pengeluaran yang wajib punya nota
        if (!$transaction->isExpense()) {
            return response()->json([
                'success' => false,
                'message' => 'Nota hanya diperlukan untuk transaksi pengeluaran'
            ], 422);
        }

        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        return DB::transaction(function () use ($request, $transaction) {
            $file = $request->file('file');
            $path = $file->store('receipts', 'public');

            $receipt = Receipt::create([
                'transaction_id' => $transaction->id,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => auth()->id(),
            ]);

            // Update status RAB items terkait
            $rabItemIds = $transaction->rabAllocations()->pluck('rab_item_id')->unique();
            foreach ($rabItemIds as $rabItemId) {
                $rabItem = RabItem::find($rabItemId);
                $this->rabService->recalculateStatus($rabItem);
            }

            return response()->json([
                'success' => true,
                'message' => 'Nota berhasil diupload',
                'data' => $receipt->load('uploader')
            ], 201);
        });
    }

    /**
     * Download receipt file
     */
    public function download(int $id)
    {
        $receipt = Receipt::findOrFail($id);

        if (!Storage::disk('public')->exists($receipt->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan'
            ], 404);
        }

        return Storage::disk('public')->download(
            $receipt->file_path,
            $receipt->original_filename
        );
    }

    /**
     * Delete receipt
     */
    public function destroy(int $id)
    {
        $receipt = Receipt::findOrFail($id);
        $transaction = $receipt->transaction;

        return DB::transaction(function () use ($receipt, $transaction) {
            // Hapus file
            if (Storage::disk('public')->exists($receipt->file_path)) {
                Storage::disk('public')->delete($receipt->file_path);
            }

            $receipt->delete();

            // Update status RAB items terkait
            $rabItemIds = $transaction->rabAllocations()->pluck('rab_item_id')->unique();
            foreach ($rabItemIds as $rabItemId) {
                $rabItem = RabItem::find($rabItemId);
                $this->rabService->recalculateStatus($rabItem);
            }

            return response()->json([
                'success' => true,
                'message' => 'Nota berhasil dihapus'
            ]);
        });
    }
}

