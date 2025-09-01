<?php

namespace App\Http\Controllers\Api\Conversation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Conversation;

class ConversationController extends Controller
{
    public function index(): JsonResponse
    {
        $conversations = Conversation::all();

        return response()->json([
            'message' => 'success',
            'data' => $conversations,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        // Kiểm tra khóa bảo mật
        if ($request->header('x-api-secret') !== env('PUBLIC_QUESTION_SECRET')) {
            return response()->json(['message' => 'Không có quyền truy cập'], 403);
        }

        $request->validate([
            'question' => 'required|string',
            'context' => 'required|string',
            'response_type' => 'required|string',
            'answer' => 'required|string',
        ]);

        // Create a new conversation
        $conversation = Conversation::create($request->all());

        return response()->json([
            'message'=> 'success',
            'data' => $conversation,
        ], 201);
    }
}
