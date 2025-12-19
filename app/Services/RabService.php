<?php

namespace App\Services;

use App\Models\RabItem;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class RabService
{
    /**
     * Recalculate status RAB item berdasarkan realisasi dan nota
     */
    public function recalculateStatus(RabItem $item): void
    {
        $realized = $item->realized_amount;
        
        // Cek apakah semua transaksi pengeluaran terkait punya nota
        $transactions = $item->transactionAllocations()
            ->with('transaction.receipts')
            ->get()
            ->pluck('transaction')
            ->unique('id')
            ->filter(fn($t) => $t->type === 'expense');

        $allTransactionsHaveReceipts = $transactions->every(fn($t) => $t->receipts->count() > 0);

        if ($realized <= 0) {
            $status = 'belum_terpenuhi';
        } elseif ($realized < $item->total_budget) {
            $status = 'sebagian_terpenuhi';
        } elseif ($realized == $item->total_budget && $allTransactionsHaveReceipts) {
            $status = 'terpenuhi';
        } else {
            $status = 'sebagian_terpenuhi';
        }

        $item->status = $status;
        $item->save();
    }

    /**
     * Validasi apakah pengeluaran melebihi sisa RAB
     */
    public function validateExpenseAgainstRab(array $rabAllocations, int $programId): array
    {
        $errors = [];

        foreach ($rabAllocations as $allocation) {
            $rabItem = RabItem::where('program_id', $programId)
                ->find($allocation['rab_item_id']);

            if (!$rabItem) {
                $errors[] = "RAB item dengan ID {$allocation['rab_item_id']} tidak ditemukan";
                continue;
            }

            $currentRealized = $rabItem->realized_amount;
            $newAmount = $allocation['amount'];
            $newRealized = $currentRealized + $newAmount;

            if ($newRealized > $rabItem->total_budget) {
                $errors[] = "Pengeluaran untuk '{$rabItem->name}' melebihi sisa RAB. Sisa: " . 
                    number_format($rabItem->remaining_budget, 0, ',', '.') . 
                    ", Mencoba: " . number_format($newAmount, 0, ',', '.');
            }
        }

        return $errors;
    }

    /**
     * Log audit untuk perubahan RAB
     */
    public function logAudit(string $action, RabItem $item, ?array $beforeData = null, ?string $ipAddress = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => 'RAB_ITEM',
            'module_id' => $item->id,
            'before_data' => $beforeData,
            'after_data' => $item->toArray(),
            'ip_address' => $ipAddress ?? request()->ip(),
        ]);
    }
}

