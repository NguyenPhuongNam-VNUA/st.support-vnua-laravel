<?php

namespace App\Http\Controllers\Api\Document;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

}
