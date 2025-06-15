<?php

use App\Http\Controllers\Api\Question\QuestionController;
use Illuminate\Support\Facades\Route;

Route::prefix('questions')->group(function () {
    Route::get('/', [QuestionController::class, 'index']);
    Route::post('/', [QuestionController::class, 'store']);
});
