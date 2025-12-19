<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Program;
use App\Models\RabItem;
use App\Models\TransactionRabItem;
use App\Models\AuditLog;
use App\Services\RabService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TransactionService
{
    protected RabService $rabService;

    public function __construct(RabService $rabService)
    {
        $this->rabService = $rabService;
    }

    /**
     * Create transaksi pemasukan
     */
    public function createIncome(Program $program, array $data): Transaction
    {
        return DB::transaction(function () use ($program, $data) {
            $transaction = Transaction::create([
                'program_id' => $program->id,
                'type' => 'income',
                'date' => $data['date'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'created_by' => auth()->id(),
            ]);

            $this->logAudit('create', $transaction);

            return $transaction;
        });
    }

    /**
     * Create transaksi pengeluaran dengan validasi RAB
     */
    public function createExpense(Program $program, array $data): Transaction
    {
        // Validasi program aktif dan punya RAB
        if (!$program->canAddTransaction()) {
            throw new \Exception('Program belum aktif atau belum memiliki RAB');
        }

        // Validasi RAB allocations
        if (empty($data['rab_allocations'])) {
            throw new \Exception('Pengeluaran harus dialokasikan ke item RAB');
        }

        // Validasi tidak melebihi sisa RAB
        $errors = $this->rabService->validateExpenseAgainstRab(
            $data['rab_allocations'],
            $program->id
        );

        if (!empty($errors)) {
            throw new \Exception(implode(', ', $errors));
        }

        // Validasi total amount sama dengan sum allocations
        $totalAllocations = collect($data['rab_allocations'])->sum('amount');
        if (abs($totalAllocations - $data['amount']) > 0.01) {
            throw new \Exception('Total alokasi RAB harus sama dengan jumlah transaksi');
        }

        return DB::transaction(function () use ($program, $data) {
            $transaction = Transaction::create([
                'program_id' => $program->id,
                'type' => 'expense',
                'date' => $data['date'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'created_by' => auth()->id(),
            ]);

            // Create RAB allocations
            foreach ($data['rab_allocations'] as $allocation) {
                TransactionRabItem::create([
                    'transaction_id' => $transaction->id,
                    'rab_item_id' => $allocation['rab_item_id'],
                    'amount' => $allocation['amount'],
                ]);

                // Update status RAB item
                $rabItem = RabItem::find($allocation['rab_item_id']);
                $this->rabService->recalculateStatus($rabItem);
            }

            $this->logAudit('create', $transaction);

            return $transaction->load('rabAllocations.rabItem');
        });
    }

    /**
     * Update transaksi
     */
    public function update(Transaction $transaction, array $data): Transaction
    {
        $beforeData = $transaction->toArray();

        return DB::transaction(function () use ($transaction, $data, $beforeData) {
            // Jika pengeluaran, perlu validasi ulang RAB
            if ($transaction->isExpense() && isset($data['rab_allocations'])) {
                $program = $transaction->program;
                
                // Hapus allocations lama
                $transaction->rabAllocations()->delete();

                // Validasi baru
                $errors = $this->rabService->validateExpenseAgainstRab(
                    $data['rab_allocations'],
                    $program->id
                );

                if (!empty($errors)) {
                    throw new \Exception(implode(', ', $errors));
                }

                // Create allocations baru
                foreach ($data['rab_allocations'] as $allocation) {
                    TransactionRabItem::create([
                        'transaction_id' => $transaction->id,
                        'rab_item_id' => $allocation['rab_item_id'],
                        'amount' => $allocation['amount'],
                    ]);

                    $rabItem = RabItem::find($allocation['rab_item_id']);
                    $this->rabService->recalculateStatus($rabItem);
                }
            }

            $transaction->update([
                'date' => $data['date'] ?? $transaction->date,
                'amount' => $data['amount'] ?? $transaction->amount,
                'description' => $data['description'] ?? $transaction->description,
            ]);

            $this->logAudit('update', $transaction, $beforeData);

            return $transaction->fresh();
        });
    }

    /**
     * Delete transaksi
     */
    public function delete(Transaction $transaction): void
    {
        $beforeData = $transaction->toArray();

        DB::transaction(function () use ($transaction, $beforeData) {
            // Jika pengeluaran, update status RAB items
            if ($transaction->isExpense()) {
                $rabItemIds = $transaction->rabAllocations()->pluck('rab_item_id')->unique();
                $transaction->rabAllocations()->delete();

                foreach ($rabItemIds as $rabItemId) {
                    $rabItem = RabItem::find($rabItemId);
                    $this->rabService->recalculateStatus($rabItem);
                }
            }

            $transaction->delete();
            $this->logAudit('delete', $transaction, $beforeData);
        });
    }

    /**
     * Log audit untuk transaksi
     */
    protected function logAudit(string $action, Transaction $transaction, ?array $beforeData = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => 'TRANSACTION',
            'module_id' => $transaction->id,
            'before_data' => $beforeData,
            'after_data' => $transaction->toArray(),
            'ip_address' => request()->ip(),
        ]);
    }
}

