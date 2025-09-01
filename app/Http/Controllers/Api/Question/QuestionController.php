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

            if ($questions->isEmpty()) {
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
                'question' => 'required|string',
                'answer' => 'required|string',
                'has_answer' => 'required|boolean',
                'topic' => 'required|string',
                'related_questions' => 'nullable|string',
            ]);

            if (Question::all()->count() >= 1) {
                //Check duplicate question
                $check_data = Http::post(config('services.python_api.base_url') .'/check-duplicate', [
                    'question' => $request->question,
                    'related_questions' => $request->related_questions ?? '',
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
                'topic' => $question->topic,
                'related_questions' => $question->related_questions,
            ]);

            $question->is_embed = $embed_data->json('is_embed');
            $question->save();

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
                'question' => 'required|string',
                'answer' => 'nullable|string',
                'has_answer' => 'required|boolean',
                'topic' => 'required|string',
                'related_questions' => 'nullable|string',
            ]);

            $question = Question::findOrFail($id);
            $question->update($request->all());

            $embed_data = Http::post(config('services.python_api.base_url') . '/embed', [
                'id' => $question->id,
                'question' => $question->question,
                'answer' => $question->answer,
                'has_answer' => (bool)$question->answer,
                'topic' => $question->topic,
                'related_questions' => $question->related_questions,
            ]);
            
            $question->is_embed = $embed_data->json('is_embed');
            $question->save();

            return response()->json([
                'status' => 'success',
                'data' => $question,
                'embed_data' => $embed_data->json(),
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

    public function embedMany(Request $request): JsonResponse
    {
        $questions = $request->input('questions', []);

        try {
            $payload = collect($questions)->map(function ($item) {
                return [
                    'id' => $item['id'],
                    'question' => $item['question'],
                    'answer' => $item['answer'] ?? null,
                    'has_answer' => (bool)($item['has_answer'] ?? false),
                    'topic' => $item['topic'] ?? 'Chưa phân loại',
                    'related_questions' => $item['related_questions'] ?? null,
                ];
            })->toArray();
        

            // Call api embed-batch
            Http::post(config('services.python_api.base_url') . '/embed-batch', [
                'questions' => $payload
            ]);

            // Update flag trong DB: is_embed = true
            Question::whereIn('id', array_column($payload, 'id'))->update(['is_embed' => true]);

            return response()->json([
                'status' => 'success',
                'message' => 'Embedding batch thành công'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi thêm nhiều câu hỏi',
                'error' => $e->getMessage(),
                'status' => 'error'
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

    public function storePublic(Request $request): JsonResponse
    {
        // Kiểm tra khóa bảo mật
        if ($request->header('x-api-secret') !== env('PUBLIC_QUESTION_SECRET')) {
            return response()->json(['message' => 'Không có quyền truy cập'], 403);
        }

        $request->validate([
            'question' => 'required|string|min:10',
        ]);

        try {
            // Create question
            $question = Question::create([
                'question' => $request->question,
                'answer' =>  null,
                'has_answer' => false,
                'topic' => 'Câu hỏi phát sinh',
                'related_questions' => null,
                'ask_count' => 1,
                'is_embed' => false,
            ]);

            return response()->json([
                'status' => 'success',
                'id' => $question->id,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi tạo câu hỏi phát sinh',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    public function increseAskCount(Request $request): JsonResponse
    {
        // Kiểm tra khóa bảo mật
        if ($request->header('x-api-secret') !== env('PUBLIC_QUESTION_SECRET')) {
            return response()->json(['message' => 'Không có quyền truy cập'], 403);
        }

        $request->validate([
            'id' => 'required|integer'
        ]);

        try {
            $question = Question::findOrFail($request->id);
            $question->ask_count +=1;
            $question->save();

            return response()->json([
                'status' => 'success',
                'data' => $question
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Lỗi khi tăng số lần hỏi',
                'error' => $th->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    public function showTopAsk(): JsonResponse
    {
        $topQuestions = Question::query()
            ->orderBy('ask_count', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $topQuestions
        ], 200);
    }
}
