<?php

namespace App\Jobs;

use App\Models\LeadImport;
use App\Models\LeadImportFailure;
use App\Mail\FailedRecordsMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GenerateFailedRecordsCsv implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3600;

    public function __construct(
        public int $importId
    ) {}

    public function handle(): void
    {
        $import = LeadImport::findOrFail(
            $this->importId
        );

        if ($import->failed_count === 0) {
            return;
        }

        $filename = 'failed_records_' . $import->id . '.csv';

        $path = 'lead-imports/' . $filename;

        $fullPath = Storage::disk('local')
            ->path($path);

        $handle = fopen($fullPath, 'w');

        if ($handle === false) {
            throw new \RuntimeException(
                'Unable to create failed records CSV.'
            );
        }

        try {
            fputcsv($handle, [
                'name',
                'email',
                'phone',
                'company',
                'reason',
            ]);

            LeadImportFailure::where(
                'lead_import_id',
                $import->id
            )
            ->orderBy('id')
            ->chunkById(
                1000,
                function ($failures) use ($handle) {
                    foreach ($failures as $failure) {
                        fputcsv($handle, [
                            $failure->name,
                            $failure->email,
                            $failure->phone,
                            $failure->company,
                            $failure->reason,
                        ]);
                    }
                }
            );
        } finally {
            fclose($handle);
        }

        // Send failed CSV by email
        Mail::to('admin@yopmail.com')->send(
            new FailedRecordsMail(
                $import,
                $fullPath
            )
        );
    }
}
