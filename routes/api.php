<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\RabItemController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ExpenseController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'user']);
    
    // Users
    Route::get('/users', [\App\Http\Controllers\Api\UserController::class, 'index']);

    // Programs
    Route::get('/programs', [ProgramController::class, 'index']);
    Route::post('/programs', [ProgramController::class, 'store']);
    Route::get('/programs/{id}', [ProgramController::class, 'show']);
    Route::put('/programs/{id}', [ProgramController::class, 'update']);
    Route::delete('/programs/{id}', [ProgramController::class, 'destroy']);
    Route::put('/programs/{id}/status', [ProgramController::class, 'updateStatus']);
    
    // Program Members
    Route::get('/programs/{id}/members', [ProgramController::class, 'getMembers']);
    Route::post('/programs/{id}/members', [ProgramController::class, 'addMember']);
    Route::post('/programs/{id}/members/approve', [ProgramController::class, 'approveMember']);
    Route::delete('/programs/{id}/members/{userId}', [ProgramController::class, 'removeMember']);
    
    // RAB Items
    Route::get('/programs/{programId}/rab-items', [RabItemController::class, 'index']);
    Route::post('/programs/{programId}/rab-items', [RabItemController::class, 'store']);
    Route::get('/rab-items/{id}', [RabItemController::class, 'show']);
    Route::put('/rab-items/{id}', [RabItemController::class, 'update']);
    Route::delete('/rab-items/{id}', [RabItemController::class, 'destroy']);
    Route::get('/programs/{programId}/rab-summary', [RabItemController::class, 'summary']);
    
    // Transactions
    Route::get('/programs/{programId}/transactions', [TransactionController::class, 'index']);
    Route::post('/programs/{programId}/transactions', [TransactionController::class, 'store']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::put('/transactions/{id}', [TransactionController::class, 'update']);
    Route::delete('/transactions/{id}', [TransactionController::class, 'destroy']);
    
    // Receipts
    Route::get('/transactions/{transactionId}/receipts', [ReceiptController::class, 'index']);
    Route::post('/transactions/{transactionId}/receipts', [ReceiptController::class, 'store']);
    Route::get('/receipts/{id}/download', [ReceiptController::class, 'download']);
    Route::delete('/receipts/{id}', [ReceiptController::class, 'destroy']);
    
    // Dashboard
    Route::get('/programs/{programId}/dashboard', [DashboardController::class, 'show']);
    
    // Audit Logs
    Route::get('/programs/{programId}/audit-logs', [AuditLogController::class, 'index']);

    // Expenses
    Route::get('/programs/{programId}/expenses', [ExpenseController::class, 'index']);
    Route::post('/programs/{programId}/expenses', [ExpenseController::class, 'store']);
    Route::get('/expenses/{id}', [ExpenseController::class, 'show']);
    Route::put('/expenses/{id}', [ExpenseController::class, 'update']);
    Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);
    Route::put('/expenses/{id}/approve', [ExpenseController::class, 'approve']);
    Route::put('/expenses/{id}/reject', [ExpenseController::class, 'reject']);
});
