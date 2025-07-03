<?php

use App\Http\Controllers\Api\Question\QuestionController;
use App\Http\Controllers\Api\Document\DocumentController;
use Illuminate\Support\Facades\Route;

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
//    Route::get('/{id}', [DocumentController::class, 'show']);
    Route::post('/', [DocumentController::class, 'store']);
//    Route::patch('/{id}', [DocumentController::class, 'update']);
//    Route::delete('/{id}', [DocumentController::class, 'destroy']);
});
