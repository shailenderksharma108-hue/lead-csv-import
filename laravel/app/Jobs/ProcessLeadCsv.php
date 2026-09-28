<?php

namespace App\Jobs;

use App\Models\LeadImport;
use App\Services\LeadCsvImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Jobs\GenerateFailedRecordsCsv;
use Illuminate\Support\Facades\DB;


class ProcessLeadCsv implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3600;

    public function __construct(
        public int $importId
    ) {}

    public function handle(
        LeadCsvImporter $importer
    ): void {
        /*
         * Lock the import row while changing its status.
         * This prevents two workers from starting the same import.
         */
        $shouldProcess = DB::transaction(
            function () {
                $import = LeadImport::whereKey(
                    $this->importId
                )
                ->lockForUpdate()
                ->firstOrFail();

                if ($import->status !== 'pending') {
                    return false;
                }

                $import->update([
                    'status' => 'processing',
                    'started_at' => now(),
                ]);

                return true;
            }
        );

        if (!$shouldProcess) {
            return;
        }

        $import = LeadImport::findOrFail(
            $this->importId
        );

        $importer->import($import);

        $import->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        if ($import->failed_count > 0) {
            GenerateFailedRecordsCsv::dispatch(
                $import->id
            );
        }
    }

    public function failed(\Throwable $exception): void
    {
        LeadImport::whereKey($this->importId)
            ->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
    }

}
