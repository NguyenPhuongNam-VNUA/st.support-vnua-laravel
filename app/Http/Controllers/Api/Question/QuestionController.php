<?php

namespace App\Http\Controllers\Api\Question;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function show($id): JsonResponse
    {
        try {
            $question = Question::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $question
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Câu hỏi không tồn tại',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 404);
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

            if (Question::all()->count() >= 1) {
                //Check duplicate question
                $check_data = Http::post(config('services.python_api.base_url') .'/check-duplicate', [
                    'question' => $request->question,
                ]);

                if ($check_data->json('is_duplicate') === true) {
                    return response()->json([
                        'message' => 'Câu hỏi đã tồn tại',
                        'question' => $check_data->json('existing_doc'),
                        'score' => $check_data->json('score_str'),
                        'status' => 'error'
                    ], 409);
                }
            }

            // Create question
            $question = Question::create($request->all());

            // Embedding data
            $embed_data = Http::post(config('services.python_api.base_url') .'/embed', [
                'id' => $question->id,
                'question' => $question->question,
                'answer' => $question->answer,
                'has_answer' => (bool)$question->answer,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $question,
                'embed_data' => $embed_data->json(),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi tạo câu hỏi',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'question' => 'required|string|max:255',
                'answer' => 'nullable|string|max:500',
                'has_answer' => 'required|boolean'
            ]);

            $question = Question::findOrFail($id);
            $question->update($request->all());

            Http::post(config('services.python_api.base_url') . '/embed', [
                'id' => $question->id,
                'question' => $question->question,
                'answer' => $question->answer,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $question
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi cập nhật câu hỏi',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $question = Question::findOrFail($id);
            $question->delete();

            Http::post(config('services.python_api.base_url') . '/delete-embed', [
                'id' => $id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Đã xoá câu hỏi'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi xoá câu hỏi',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    public function destroyMany(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['message' => 'Danh sách ID rỗng'], 400);
        }

        try {
            Question::whereIn('id', $ids)->delete();

            // Gọi sang Python
            Http::post(config('services.python_api.base_url') . '/delete-embed-many', [
                'ids' => $ids
            ]);

            return response()->json(['message' => 'Đã xoá nhiều câu hỏi']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi xoá nhiều câu hỏi',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function add_excel(Request $request): JsonResponse
    {
        try {
            $data = $request->input('newQuestions', []);
            $saved_questions = [];
            // Create question
            foreach ($data as $item) {
                $question = Question::create([
                    'question' => $item['question'],
                    'answer' => $item['answer'] ?? null,
                    'has_answer' => (bool)($item['answer'] ?? false),
                ]);

                $saved_questions[] = $question;
            }
            //Log::info($saved_questions);
            $payload = collect($saved_questions)->map(function ($item) {
                return [
                    'id' => $item->id,
                    'question' => $item->question,
                    'answer' => $item->answer,
                    'has_answer' => (bool)$item->answer,
                ];
            })->toArray();
//            Log::info($payload);
            // Embedding data
            Http::post(config('services.python_api.base_url') . '/embed-batch', [
                'questions' => $payload
            ]);

            return response()->json([
                'status' => 'success',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi thêm câu hỏi từ Excel',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    public function update_duplicates(Request $request): JsonResponse
    {
        try {
            $data = $request->input('duplicateQuestions', []);

            if (empty($data)) {
                return response()->json([
                    'message' => 'Danh sách dữ liệu trống',
                    'status' => 'error'
                ], 400);
            }

            $updatedQuestions = [];

            foreach ($data as $item) {
                $question = Question::find($item['id']);

                if (!$question) {
                    continue; // Bỏ qua nếu không tìm thấy
                }

                // Cập nhật nội dung câu hỏi
                $question->question = $item['question'];
                $question->answer = $item['answer'] ?? null;
                $question->has_answer = (bool)($item['answer'] ?? false);
                $question->save();

                $updatedQuestions[] = $question;

                // Gửi sang Python để cập nhật lại embedding
                Http::post(config('services.python_api.base_url') . '/embed', [
                    'id' => $question->id,
                    'question' => $question->question,
                    'answer' => $question->answer,
                    'has_answer' => $question->has_answer
                ]);
            }

            return response()->json([
                'message' => 'Đã cập nhật và embedding lại các câu hỏi thành công',
                'updated' => $updatedQuestions,
                'status' => 'success'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi cập nhật câu hỏi trùng lặp',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
}
