<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Question\QuestionController;
use App\Http\Controllers\Api\Document\DocumentController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('questions')->group(function () {
        Route::get('/', [QuestionController::class, 'index']);
        Route::get('/{id}', [QuestionController::class, 'show']);
        Route::post('/', [QuestionController::class, 'store']);
        Route::post('/excel', [QuestionController::class, 'add_excel']);
        Route::patch('/{id}', [QuestionController::class, 'update']);
        Route::put('/update-duplicates', [QuestionController::class, 'update_duplicates']);
        Route::delete('/{id}', [QuestionController::class, 'destroy']);
        Route::delete('/', [QuestionController::class, 'destroyMany']);
    });

    Route::prefix('documents')->group(function () {
        Route::get('/', [DocumentController::class, 'index']);
        Route::post('/', [DocumentController::class, 'store']);
        Route::delete('/{id}', [DocumentController::class, 'destroy']);
        Route::post('/update-status', [DocumentController::class, 'update_status']);
    });
});

Route::post('/public/questions', [QuestionController::class, 'storePublic']);
Route::get('/pdf-view', function (\Illuminate\Http\Request $request) {
    $path = $request->query('path'); // ví dụ: "documents/abc.pdf"

    if (!$path || !Storage::disk('public')->exists($path)) {
        return response()->json(['message' => 'File not found'], 404);
    }

    $file = Storage::disk('public')->get($path);

    return response($file, 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Content-Disposition', 'inline; filename="preview.pdf"');
});
