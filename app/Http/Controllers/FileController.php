<?php

namespace App\Http\Controllers;

use App\Events\CsvUploadFinished;
use App\Jobs\ProcessUpdate;
use App\Models\File;
use App\Models\Product;
use Illuminate\Bus\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class FileController extends Controller
{
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
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls',
        ]);

        $csv_file = $request->file('file');
        $csv_name_og =  $csv_file->getClientOriginalName();
        $csv_name =  $csv_file->getClientOriginalName() . '_' . uniqid() . '_' . time();
        $csv_size = $csv_file->getSize();

        $data = file(request()->file);
        $data = $this->removeNonUTF8Characters($data);

        // Chunking file
        $chunks = array_chunk($data, 1000);
        $header = [];

        $jobs = [];
        foreach ($chunks as $key => $chunk) {
            $rows = array_map('str_getcsv', $chunk);

            if ($key === 0) {
                $header = $rows[0];
                $header[0] = trim($header[0], "\xEF\xBB\xBF");
                unset($rows[0]);
            }
            $jobs[] = new ProcessUpdate($rows, $header);
        }

        $batch = Bus::batch($jobs)->dispatch();

        $file = new File();
        $file->name = $csv_name;
        $file->name_og = $csv_name_og;
        $file->file_size = $csv_size;
        $file->status = 0;
        $file->job_batch_id = $batch->id;
        $file->save();

        return redirect('/')->with('success-msg', 'File Upload Success!');
    }

    public function removeNonUTF8Characters($text)
    {
        return preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);
    }
}
