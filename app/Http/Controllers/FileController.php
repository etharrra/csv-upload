<?php

namespace App\Http\Controllers;

use App\Http\Requests\FileUploadRequest;
use App\Models\File;
use App\Services\FileService;

class FileController extends Controller
{

    protected $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Index function.
     *
     * @return void
     */
    public function index()
    {
        $files = File::all();
        return view('welcome', ['files' => $files]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FileUploadRequest $request)
    {
        $uploadedFile = $request->file('file');

        // Process and store the file using the FileService
        $file = $this->fileService->processAndStoreFile($uploadedFile);

        return redirect('/')->with('success-msg', 'File Upload Success!');
    }
}
