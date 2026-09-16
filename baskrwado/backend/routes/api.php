<?php

use App\Http\Controllers\Api\AdminCaseController;
use App\Http\Controllers\Api\CaseController;
use App\Http\Controllers\Api\ServiceCatalogController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/services', ServiceCatalogController::class)->middleware('throttle:120,1');

Route::middleware('throttle:30,1')->group(function (): void {
    Route::post('/cases', [CaseController::class, 'store']);
    Route::post('/cases/{publicId}/answers', [CaseController::class, 'answers']);
    Route::get('/cases/{publicId}', [CaseController::class, 'show']);
});

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive']);

Route::prefix('/admin')->middleware(['admin.key', 'throttle:120,1'])->group(function (): void {
    Route::get('/cases', [AdminCaseController::class, 'index']);
    Route::get('/cases/{publicId}', [AdminCaseController::class, 'show']);
    Route::patch('/cases/{publicId}/status', [AdminCaseController::class, 'updateStatus']);
});
