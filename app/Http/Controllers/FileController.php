<?php

namespace App\Http\Controllers;

use App\Events\CsvFileUploaded;
use App\Http\Requests\FileUploadRequest;
use App\Http\Requests\PresignedUploadRequest;
use App\Models\File;
use App\Services\FileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FileController extends Controller
{
    public function __construct(
        protected FileService $fileService
    ) {
    }

    /**
     * Display a listing of uploaded files.
     */
    public function index(): View
    {
        $files = $this->fileService->getFiles();

        return view('welcome', ['files' => $files]);
    }

    /**
     * Store a newly uploaded file and dispatch processing.
     */
    public function store(FileUploadRequest $request): RedirectResponse
    {
        try {
            $uploadedFile = $request->file('file');
            $file = $this->fileService->processAndStoreFile($uploadedFile);

            // Broadcast the file upload event
            CsvFileUploaded::dispatch(
                $file->name_og,
                $file->job_batch_id,
                $file->id,
                $file->created_at->toISOString()
            );

            return redirect()->route('files.index')->with('success-msg', 'File Upload Success!');
        } catch (\Throwable $e) {
            Log::error('File upload failed: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('files.index')->with('error-msg', 'File upload failed. Please try again.');
        }
    }

    public function presign(PresignedUploadRequest $request): JsonResponse
    {
        try {
            $upload = $this->fileService->createPresignedUpload(
                (string) $request->validated('name'),
                (int) $request->validated('size'),
                $request->validated('type')
            );

            return response()->json($upload);
        } catch (\Throwable $e) {
            Log::error('Presigned upload URL generation failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'message' => 'Could not prepare the upload. Please try again.',
            ], 500);
        }
    }

    public function cancelUpload(File $file): JsonResponse
    {
        $this->fileService->cancelPendingUpload($file);

        return response()->json(['ok' => true]);
    }
}
