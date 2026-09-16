<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminCaseController;
use App\Http\Controllers\Api\AdminDocumentController;
use App\Http\Controllers\Api\AdminStaffController;
use App\Http\Controllers\Api\CaseController;
use App\Http\Controllers\Api\CaseDocumentController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ServiceCatalogController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/services', ServiceCatalogController::class)->middleware('throttle:120,1');

Route::middleware('throttle:30,1')->group(function (): void {
    Route::post('/cases', [CaseController::class, 'store']);
    Route::post('/cases/{publicId}/answers', [CaseController::class, 'answers']);
    Route::get('/cases/{publicId}', [CaseController::class, 'show']);
    Route::post('/cases/{publicId}/documents', [CaseDocumentController::class, 'store']);
    Route::post('/cases/{publicId}/payment-order', [PaymentController::class, 'createOrder']);
    Route::post('/payments/razorpay/verify', [PaymentController::class, 'verify']);
});

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive']);
Route::post('/webhooks/razorpay', [PaymentController::class, 'webhook']);

Route::prefix('/admin')->group(function (): void {
    Route::post('/auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['admin.auth', 'throttle:180,1'])->group(function (): void {
        Route::get('/auth/me', [AdminAuthController::class, 'me']);
        Route::post('/auth/logout', [AdminAuthController::class, 'logout']);

        Route::get('/cases', [AdminCaseController::class, 'index']);
        Route::get('/cases/{publicId}', [AdminCaseController::class, 'show']);
        Route::patch('/cases/{publicId}', [AdminCaseController::class, 'update']);
        Route::post('/cases/{publicId}/notes', [AdminCaseController::class, 'addNote']);

        Route::get('/staff', [AdminStaffController::class, 'index']);
        Route::post('/staff', [AdminStaffController::class, 'store']);

        Route::get('/documents/{documentId}/download', [AdminDocumentController::class, 'download']);
        Route::patch('/documents/{documentId}/verify', [AdminDocumentController::class, 'verify']);
    });
});
