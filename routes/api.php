<?php

use App\Http\Controllers\Api\Question\QuestionController;
use Illuminate\Support\Facades\Route;

Route::prefix('questions')->group(function () {
    Route::get('/', [QuestionController::class, 'index']);
    Route::get('/{id}', [QuestionController::class, 'show']);
    Route::post('/', [QuestionController::class, 'store']);
    Route::patch('/{id}', [QuestionController::class, 'update']);
    Route::delete('/{id}', [QuestionController::class, 'destroy']);
    Route::delete('/', [QuestionController::class, 'destroyMany']);
});
