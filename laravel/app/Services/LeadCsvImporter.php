<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadImport;
use App\Models\LeadImportFailure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LeadCsvImporter
{
    /**
     * Number of CSV rows processed at a time.
     */
    private const CHUNK_SIZE = 1000;

    /**
     * Process the complete CSV file.
     */
    public function import(LeadImport $import): void
    {
        $filePath = storage_path(
            'app/private/' . $import->file_path
        );

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new \RuntimeException(
                'Unable to open CSV file.'
            );
        }

        try {
            /*
             * -------------------------------------------------
             * 1. Read and validate CSV header
             * -------------------------------------------------
             */
            $headers = fgetcsv($handle);

            if ($headers === false) {
                throw new \RuntimeException(
                    'CSV file is empty.'
                );
            }

            $headers = array_map(
                fn ($header) => strtolower(trim($header)),
                $headers
            );

            $requiredHeaders = [
                'name',
                'email',
                'phone',
                'company',
            ];

            if (array_diff($requiredHeaders, $headers)) {
                throw new \RuntimeException(
                    'CSV must contain name, email, phone and company columns.'
                );
            }

            /*
             * -------------------------------------------------
             * 2. Count total records
             * -------------------------------------------------
             */
            $totalRecords = 0;

            while (fgetcsv($handle) !== false) {
                $totalRecords++;
            }

            $import->update([
                'total_records' => $totalRecords,
                'processed_records' => 0,
                'success_count' => 0,
                'failed_count' => 0,
            ]);

            /*
             * -------------------------------------------------
             * 3. Reset file pointer
             * -------------------------------------------------
             */
            rewind($handle);

            // Skip header.
            fgetcsv($handle);

            $success = 0;
            $failed = 0;
            $processed = 0;

            $rows = [];

            /*
             * -------------------------------------------------
             * 4. Process CSV in chunks
             * -------------------------------------------------
             */
            while (($row = fgetcsv($handle)) !== false) {

                $processed++;

                $rows[] = [
                    'row_number' => $processed + 1,
                    'data' => $this->mapRow(
                        $headers,
                        $row
                    ),
                ];

                if (count($rows) >= self::CHUNK_SIZE) {

                    [
                        $chunkSuccess,
                        $chunkFailed
                    ] = $this->processChunk(
                        $import,
                        $rows
                    );

                    $success += $chunkSuccess;
                    $failed += $chunkFailed;

                    $rows = [];

                    /*
                     * Update progress after every chunk.
                     */
                    $import->update([
                        'processed_records' => $processed,
                        'success_count' => $success,
                        'failed_count' => $failed,
                    ]);
                }
            }

            /*
             * -------------------------------------------------
             * 5. Process remaining rows
             * -------------------------------------------------
             */
            if (!empty($rows)) {

                [
                    $chunkSuccess,
                    $chunkFailed
                ] = $this->processChunk(
                    $import,
                    $rows
                );

                $success += $chunkSuccess;
                $failed += $chunkFailed;
            }

            /*
             * -------------------------------------------------
             * 6. Final progress update
             * -------------------------------------------------
             */
            $import->update([
                'total_records' => $totalRecords,
                'processed_records' => $processed,
                'success_count' => $success,
                'failed_count' => $failed,
            ]);

        } finally {
            fclose($handle);
        }
    }

    /**
     * Process one chunk.
     */
    private function processChunk(
        LeadImport $import,
        array $rows
    ): array {
        $validRows = [];
        $failures = [];

        /*
         * Emails appearing more than once
         * in the current chunk.
         */
        $chunkEmailCounts = [];

        /*
         * -------------------------------------------------
         * 1. Validate rows
         * -------------------------------------------------
         */
        foreach ($rows as $row) {

            $data = $row['data'];
            $rowNumber = $row['row_number'];

            /*
             * Normalize fields.
             */
            $data['name'] = isset($data['name'])
                ? trim($data['name'])
                : null;

            $data['email'] = isset($data['email'])
                ? strtolower(trim($data['email']))
                : null;

            $data['phone'] = isset($data['phone'])
                ? trim($data['phone'])
                : null;

            $data['company'] = isset($data['company'])
                ? trim($data['company'])
                : null;

            /*
             * Validate fields.
             */
            $validator = Validator::make(
                $data,
                [
                    'name' => [
                        'required',
                        'string',
                        'max:255',
                    ],

                    'email' => [
                        'required',
                        'email',
                        'max:255',
                    ],

                    'phone' => [
                        'required',
                        'regex:/^[0-9]{10,15}$/',
                    ],

                    'company' => [
                        'required',
                        'string',
                        'max:255',
                    ],
                ]
            );

            if ($validator->fails()) {

                $failures[] = $this->failureData(
                    $import,
                    $rowNumber,
                    $data,
                    implode(
                        ' ',
                        $validator->errors()->all()
                    )
                );

                continue;
            }

            $email = $data['email'];

            /*
             * Count email inside current chunk.
             */
            $chunkEmailCounts[$email] =
                ($chunkEmailCounts[$email] ?? 0) + 1;

            $validRows[] = [
                'row_number' => $rowNumber,

                'data' => [
                    'name' => $data['name'],
                    'email' => $email,
                    'phone' => $data['phone'],
                    'company' => $data['company'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];
        }

        /*
         * -------------------------------------------------
         * 2. Remove duplicate emails inside current chunk
         * -------------------------------------------------
         */
        $uniqueRows = [];

        foreach ($validRows as $row) {

            $email = $row['data']['email'];

            if ($chunkEmailCounts[$email] > 1) {

                $failures[] = $this->failureData(
                    $import,
                    $row['row_number'],
                    $row['data'],
                    'Duplicate email in CSV file.'
                );

                continue;
            }

            $uniqueRows[] = $row;
        }

        $validRows = $uniqueRows;

        /*
         * -------------------------------------------------
         * 3. Check emails already seen in this import
         * -------------------------------------------------
         *
         * This handles duplicates across chunks.
         */
        $emails = array_values(
            array_unique(
                array_map(
                    fn ($row) => $row['data']['email'],
                    $validRows
                )
            )
        );

        $seenEmails = [];

        if (!empty($emails)) {

            $seenEmails = DB::table(
                'import_seen_emails'
            )
            ->where('lead_import_id', $import->id)
            ->whereIn('email', $emails)
            ->pluck('email')
            ->map(
                fn ($email) => strtolower($email)
            )
            ->flip()
            ->all();
        }

        $newRows = [];

        foreach ($validRows as $row) {

            $email = $row['data']['email'];

            if (isset($seenEmails[$email])) {

                $failures[] = $this->failureData(
                    $import,
                    $row['row_number'],
                    $row['data'],
                    'Duplicate email in CSV file.'
                );

                continue;
            }

            $newRows[] = $row;
        }

        $validRows = $newRows;

        /*
         * -------------------------------------------------
         * 4. Store emails seen in this CSV
         * -------------------------------------------------
         */
        if (!empty($validRows)) {

            $seenRows = [];

            foreach ($validRows as $row) {

                $seenRows[] = [
                    'lead_import_id' => $import->id,
                    'email' => $row['data']['email'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('import_seen_emails')
                ->insertOrIgnore($seenRows);
        }

        /*
         * -------------------------------------------------
         * 5. Check existing emails in leads table
         * -------------------------------------------------
         */
        $emails = array_values(
            array_unique(
                array_map(
                    fn ($row) => $row['data']['email'],
                    $validRows
                )
            )
        );

        $existingEmails = [];

        if (!empty($emails)) {

            $existingEmails = Lead::query()
                ->whereIn('email', $emails)
                ->pluck('email')
                ->map(
                    fn ($email) => strtolower($email)
                )
                ->flip()
                ->all();
        }

        /*
         * -------------------------------------------------
         * 6. Prepare insert rows
         * -------------------------------------------------
         */
        $insertRows = [];

        foreach ($validRows as $row) {

            $email = $row['data']['email'];

            if (isset($existingEmails[$email])) {

                $failures[] = $this->failureData(
                    $import,
                    $row['row_number'],
                    $row['data'],
                    'Duplicate email already exists.'
                );

                continue;
            }

            $insertRows[] = $row['data'];
        }

        /*
         * -------------------------------------------------
         * 7. Bulk insert leads
         * -------------------------------------------------
         */
        if (!empty($insertRows)) {
            Lead::insert($insertRows);
        }

        /*
         * -------------------------------------------------
         * 8. Bulk insert failures
         * -------------------------------------------------
         */
        if (!empty($failures)) {
            LeadImportFailure::insert($failures);
        }

        return [
            count($insertRows),
            count($failures),
        ];
    }

    /**
     * Convert CSV row into associative array.
     */
    private function mapRow(
        array $headers,
        array $row
    ): array {
        $data = [];

        foreach ($headers as $index => $header) {
            $data[$header] = $row[$index] ?? null;
        }

        return $data;
    }

    /**
     * Prepare failed record.
     */
    private function failureData(
        LeadImport $import,
        int $rowNumber,
        array $data,
        string $reason
    ): array {
        return [
            'lead_import_id' => $import->id,

            'row_number' => $rowNumber,

            'name' => $data['name'] ?? null,

            'email' => $data['email'] ?? null,

            'phone' => $data['phone'] ?? null,

            'company' => $data['company'] ?? null,

            'reason' => $reason,

            'created_at' => now(),

            'updated_at' => now(),
        ];
    }
}
