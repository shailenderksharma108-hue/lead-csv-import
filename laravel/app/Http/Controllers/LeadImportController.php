<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeadImport;
use App\Jobs\ProcessLeadCsv;
use Illuminate\Support\Facades\Storage;


class LeadImportController extends Controller
{
    public function show(int $id)
    {
        $import = LeadImport::findOrFail($id);

        return response()->json([
            'id' => $import->id,
            'filename' => $import->original_filename,
            'status' => $import->status,

            'total_records' => $import->total_records,
            'processed_records' => $import->processed_records,

            'success_count' => $import->success_count,
            'failed_count' => $import->failed_count,

            'error_message' => $import->error_message,

            'started_at' => $import->started_at,
            'completed_at' => $import->completed_at,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:512000',
            ],
        ]);

        $file = $request->file('file');

        $path = $file->store('lead-imports');

        $import = LeadImport::create([
            // Temporary assignment setup
            'user_id' => 1,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status' => 'pending',
        ]);

        // Send CSV processing to Laravel Queue
        ProcessLeadCsv::dispatch($import->id);

        return response()->json([
            'message' => 'CSV uploaded successfully.',
            'import' => $import,
        ], 201);
    }

    public function downloadFailedRecords(int $id)
    {
        $import = LeadImport::findOrFail($id);

        if ($import->failed_count === 0) {
            return response()->json([
                'message' => 'No failed records found for this import.',
            ], 404);
        }

        $filename = 'failed_records_' . $import->id . '.csv';

        $path = 'lead-imports/' . $filename;

        $fullPath = Storage::disk('local')->path($path);

        if (!file_exists($fullPath)) {
            return response()->json([
                'message' => 'Failed records CSV is not available yet.',
            ], 404);
        }

        return response()->download(
            $fullPath,
            $filename,
            [
                'Content-Type' => 'text/csv',
            ]
        );
    }


}
