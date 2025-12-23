<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display dashboard data for a program
     */
    public function show(Request $request, int $programId)
    {
        $program = Program::with(['rabItems'])
            ->findOrFail($programId);
        
        // Calculate totals
        $totalBudget = $program->total_budget;
        $totalIncome = $program->total_income;
        $totalExpense = $program->total_expense;
        $balance = $program->balance;

        // RAB Items progress
        $rabItemsProgress = $program->rabItems->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'total_budget' => $item->total_budget,
                'realized_amount' => $item->realized_amount,
                'remaining_budget' => $item->remaining_budget,
                'status' => $item->status,
                'percentage' => $item->total_budget > 0 
                    ? round(($item->realized_amount / $item->total_budget) * 100, 2) 
                    : 0,
            ];
        });

        // Category breakdown (group by category)
        $categoryBreakdown = $program->rabItems
            ->groupBy('category')
            ->map(function ($items, $category) {
                return [
                    'name' => $category ?: 'Lain-lain',
                    'allocated_budget' => $items->sum('total_budget'),
                    'spent_amount' => $items->sum(fn($item) => $item->realized_amount),
                    'remaining_budget' => $items->sum(fn($item) => $item->remaining_budget),
                    'percentage_spent' => $items->sum('total_budget') > 0
                        ? round(($items->sum(fn($item) => $item->realized_amount) / $items->sum('total_budget')) * 100, 2)
                        : 0,
                ];
            })
            ->values();

        // Recent transactions
        $recentTransactions = $program->transactions()
            ->with(['creator', 'rabAllocations.rabItem', 'receipts'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Warnings
        $warnings = [];
        
        // Check RAB items without receipts
        foreach ($program->rabItems as $item) {
            $transactionsWithoutReceipts = $item->transactionAllocations()
                ->with('transaction.receipts')
                ->get()
                ->pluck('transaction')
                ->unique('id')
                ->filter(fn($t) => $t->type === 'expense' && $t->receipts->count() === 0);

            if ($transactionsWithoutReceipts->count() > 0) {
                $warnings[] = [
                    'type' => 'missing_receipts',
                    'message' => "Item RAB '{$item->name}' memiliki {$transactionsWithoutReceipts->count()} transaksi tanpa nota",
                    'rab_item_id' => $item->id,
                ];
            }

            if ($item->status === 'belum_terpenuhi' && $item->realized_amount == 0) {
                $warnings[] = [
                    'type' => 'unfulfilled_rab',
                    'message' => "Item RAB '{$item->name}' belum terpenuhi",
                    'rab_item_id' => $item->id,
                ];
            }
        }

        // Account breakdown (mock data - bisa disesuaikan dengan struktur akun yang sebenarnya)
        $accountBreakdown = [
            'cash' => $totalIncome * 0.3, // Contoh: 30% tunai
            'bank' => $totalIncome * 0.4, // Contoh: 40% bank
            'scholarship' => $totalIncome * 0.1,
            'current_account' => $totalIncome * 0.1,
            'virtual_account' => $totalIncome * 0.1,
            'leave' => 0,
        ];

        // Payment details (mock - bisa disesuaikan)
        $paymentDetails = [
            [
                'name' => 'SPP Reguler',
                'cash' => $totalExpense * 0.3,
                'bank' => 0,
                'scholarship' => 0,
                'current_account' => 0,
                'virtual_account' => $totalExpense * 0.7,
                'leave' => 0,
            ],
            [
                'name' => 'SPP Paket',
                'cash' => 0,
                'bank' => 0,
                'scholarship' => 0,
                'current_account' => 0,
                'virtual_account' => 0,
                'leave' => 0,
            ],
            [
                'name' => 'Wisuda',
                'cash' => 0,
                'bank' => 0,
                'scholarship' => 0,
                'current_account' => 0,
                'virtual_account' => $totalExpense * 0.3,
                'leave' => 0,
            ],
        ];

        // Members spending chart data from Transactions (source of truth for dashboard)
        $memberSpending = \App\Models\ProgramUserRole::where('program_id', $programId)
            ->where('program_user_roles.status', 'approved')
            ->with('user')
            ->get()
            ->map(function ($role) use ($programId) {
                // Sum all expense transactions attributed to this user
                $totalSpent = \App\Models\Transaction::where('program_id', $programId)
                    ->where('type', 'expense')
                    ->where('created_by', $role->user_id)
                    ->sum('amount');
                
                return [
                    'name' => $role->user->full_name ?: $role->user->name,
                    'amount' => (float)$totalSpent,
                ];
            })
            ->filter(fn($m) => $m['amount'] > 0)
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'program' => [
                    'id' => $program->id,
                    'name' => $program->name,
                    'status' => $program->status,
                    'total_budget' => $totalBudget,
                    'total_income' => $totalIncome,
                    'total_expense' => $totalExpense,
                    'balance' => $balance,
                    'total_members' => $program->total_members,
                ],
                'account_breakdown' => $accountBreakdown,
                'category_breakdown' => $categoryBreakdown,
                'rab_items_progress' => $rabItemsProgress,
                'recent_transactions' => $recentTransactions,
                'member_spending' => $memberSpending,
                'warnings' => $warnings,
            ]
        ]);
    }
}
