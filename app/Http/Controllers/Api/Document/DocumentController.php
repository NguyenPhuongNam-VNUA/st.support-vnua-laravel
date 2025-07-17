<?php

namespace App\Http\Controllers\Api\Document;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    public function index(): JsonResponse
    {
        $documents = Document::all();

        return response()->json([
            'message' => 'List of documents',
            'data' => $documents,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => 'required|file|mimes:pdf,doc,docx',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents', 'public'); // lưu vào storage/app/public/documents

        $document = Document::create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'file_path' => 'storage/' . $path,
            'file_type' => $file->getClientOriginalExtension(),
        ]);

        return response()->json([
            'message' => 'Tài liệu đã được lưu',
            'data' => $document,
        ], 201);
    }

    public function destroy($id): JsonResponse
    {
        $document = Document::find($id);

        if (!$document) {
            return response()->json(['message' => 'Không tìm thấy tài liệu'], 404);
        }

        // Xoá file vật lý
        $path = str_replace('storage/', '', $document->file_path); // chuyển về dạng "documents/abc.pdf"
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        //Xóa embedding
        try {
            $response = Http::post(config('services.python_api.base_url') . '/delete-doc', [
                'file_path' => $path,
            ]);

            if (!$response->successful()) {
                Log::warning('Xoá embedding thất bại: ' . $response->body());
            }

            // Xoá bản ghi DB
            $document->delete();

            return response()->json(['message' => 'Tài liệu đã được xoá']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi xoá embedding: ' . $e->getMessage()], 500);
        }
    }

    public function update_status(Request $request): JsonResponse
    {
        $request->validate([
            'file_path' => 'required|string',
            'chunk_size' => 'nullable|integer',
            'chunk_overlap' => 'nullable|integer',
        ]);

        $document = Document::where('file_path', 'storage/' . $request->file_path)->first();

        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        $document->is_embed = true;
        $document->chunk_size = $request->chunk_size;
        $document->chunk_overlap = $request->chunk_overlap;
        $document->save();

        return response()->json(['message' => 'Đã cập nhật trạng thái embedding']);
    }
}
