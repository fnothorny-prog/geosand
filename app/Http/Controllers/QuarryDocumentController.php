<?php

namespace App\Http\Controllers;

use App\Models\Quarry;
use App\Models\QuarryDocument;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuarryDocumentController extends Controller
{
    use ApiResponse;

    // Allowed MIME types
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/jpg',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    /**
     * List all documents for a quarry.
     */
    public function index($quarryId)
    {
        $quarry = Quarry::find($quarryId);
        if (!$quarry) {
            return $this->errorResponse('Quarry not found', 404);
        }

        $documents = QuarryDocument::with('uploader:id,name,email')
            ->where('quarry_id', $quarryId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse([
            'documents' => $documents,
            'quarry'    => ['id' => $quarry->id, 'name' => $quarry->name],
        ]);
    }

    /**
     * Upload a new document for a quarry.
     * Operators can only upload for their assigned quarry.
     * Admins can upload for any quarry.
     */
    public function store(Request $request, $quarryId)
    {
        $quarry = Quarry::find($quarryId);
        if (!$quarry) {
            return $this->errorResponse('Quarry not found', 404);
        }

        // Check permissions: operators can only upload for their assigned quarry
        $user = $request->user();
        if ($user->role === 'operator' && $user->quarry_id != $quarryId) {
            return $this->errorResponse('You can only upload documents for your assigned quarry', 403);
        }

        $request->validate([
            'file'          => 'required|file|max:10240', // 10 MB max
            'document_type' => 'required|string|max:100',
            'title'         => 'required|string|max:255',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $file = $request->file('file');

        // Validate MIME type
        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES)) {
            return $this->errorResponse(
                'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX',
                422
            );
        }

        // Validate file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return $this->errorResponse('File size must not exceed 10 MB', 422);
        }

        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $uniqueName = 'quarry_' . $quarryId . '_' . Str::uuid() . '.' . $extension;
        $path = 'quarry-documents/' . $quarryId . '/' . $uniqueName;

        // Store file
        Storage::disk('public')->putFileAs(
            'quarry-documents/' . $quarryId,
            $file,
            $uniqueName
        );

        $document = QuarryDocument::create([
            'quarry_id'     => $quarryId,
            'uploaded_by'   => $request->user()->id,
            'document_type' => $request->document_type,
            'title'         => $request->title,
            'file_name'     => $file->getClientOriginalName(),
            'file_path'     => $path,
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'notes'         => $request->notes,
        ]);

        // Audit log
        \App\Models\AuditLog::log(
            'upload',
            "Uploaded document '{$document->title}' for quarry: {$quarry->name}",
            'QuarryDocument',
            $document->id,
            null,
            ['title' => $document->title, 'type' => $document->document_type]
        );

        return $this->successResponse([
            'document' => $document->load('uploader:id,name,email'),
        ], 'Document uploaded successfully', 201);
    }

    /**
     * Download / get a specific document.
     */
    public function show($quarryId, $documentId)
    {
        $document = QuarryDocument::where('quarry_id', $quarryId)
            ->where('id', $documentId)
            ->first();

        if (!$document) {
            return $this->errorResponse('Document not found', 404);
        }

        return $this->successResponse(['document' => $document->load('uploader:id,name,email')]);
    }

    /**
     * Delete a document (Admin only).
     */
    public function destroy(Request $request, $quarryId, $documentId)
    {
        $document = QuarryDocument::where('quarry_id', $quarryId)
            ->where('id', $documentId)
            ->first();

        if (!$document) {
            return $this->errorResponse('Document not found', 404);
        }

        // Delete file from storage
        Storage::disk('public')->delete($document->file_path);

        // Audit log
        \App\Models\AuditLog::log(
            'delete',
            "Deleted document '{$document->title}' from quarry ID {$quarryId}",
            'QuarryDocument',
            $document->id,
            ['title' => $document->title, 'type' => $document->document_type],
            null
        );

        $document->delete();

        return $this->successResponse(null, 'Document deleted successfully');
    }
}
