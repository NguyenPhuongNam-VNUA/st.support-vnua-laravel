<?php

namespace App\Http\Controllers\Api\Question;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class QuestionController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $questions = Question::all();

            if (!$questions) {
                return response()->json([
                    'message' => 'Không có câu hỏi nào',
                    'status' => 'error'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $questions
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách câu hỏi',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'question' => 'required|string|max:255',
                'answer' => 'nullable|string|max:500',
                'has_answer' => 'required|boolean'
            ]);

            $question = Question::create($request->all());

            Http::post('http://127.0.0.1:5000/api/embed', [
                'id' => $question->id,
                'question' => $question->question,
                'answer' => $question->answer,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $question
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi tạo câu hỏi',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
}
