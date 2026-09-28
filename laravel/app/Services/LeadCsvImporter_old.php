<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadImport;
use App\Models\LeadImportFailure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LeadCsvImporter_old
{
    private const CHUNK_SIZE = 1000;

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

            $total = 0;
            $success = 0;
            $failed = 0;

            $validRows = [];
            $failures = [];

            while (($row = fgetcsv($handle)) !== false) {
                $total++;

                $data = $this->mapRow(
                    $headers,
                    $row
                );

                $rowNumber = $total + 1;

                $validator = Validator::make(
                    $data,
                    [
                        'name' => ['required', 'string', 'max:255'],
                        'email' => ['required', 'email', 'max:255'],
                        'phone' => ['required', 'string', 'max:50'],
                        'company' => ['required', 'string', 'max:255'],
                    ]
                );

                if ($validator->fails()) {
                    $failures[] = [
                        'lead_import_id' => $import->id,
                        'row_number' => $rowNumber,
                        'name' => $data['name'] ?? null,
                        'email' => $data['email'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'company' => $data['company'] ?? null,
                        'reason' => implode(
                            ' ',
                            $validator->errors()->all()
                        ),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $failed++;

                    continue;
                }

                $email = strtolower(
                    trim($data['email'])
                );

                $data['email'] = $email;

                $alreadyInDatabase = Lead::where(
                    'email',
                    $email
                )->exists();

                $alreadyInCurrentFile = collect($validRows)
                    ->contains(
                        fn ($existing) =>
                            $existing['email'] === $email
                    );

                if (
                    $alreadyInDatabase ||
                    $alreadyInCurrentFile
                ) {
                    $failures[] = [
                        'lead_import_id' => $import->id,
                        'row_number' => $rowNumber,
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'],
                        'company' => $data['company'],
                        'reason' => $alreadyInDatabase
                            ? 'Duplicate email already exists.'
                            : 'Duplicate email in CSV file.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $failed++;

                    continue;
                }

                $validRows[] = [
                    'name' => trim($data['name']),
                    'email' => $data['email'],
                    'phone' => trim($data['phone']),
                    'company' => trim($data['company']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($validRows) >= self::CHUNK_SIZE) {
                    $inserted = $this->insertLeads(
                        $validRows
                    );

                    $success += $inserted;

                    $validRows = [];
                }

                if (count($failures) >= self::CHUNK_SIZE) {
                    LeadImportFailure::insert(
                        $failures
                    );

                    $failures = [];
                }

                $this->updateProgress(
                    $import,
                    $total,
                    $success,
                    $failed
                );
            }

            if (!empty($validRows)) {
                $success += $this->insertLeads(
                    $validRows
                );
            }

            if (!empty($failures)) {
                LeadImportFailure::insert(
                    $failures
                );
            }

            $this->updateProgress(
                $import,
                $total,
                $success,
                $failed
            );
        } finally {
            fclose($handle);
        }
    }

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

    private function insertLeads(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        Lead::insert($rows);

        return count($rows);
    }

    private function updateProgress(
        LeadImport $import,
        int $total,
        int $success,
        int $failed
    ): void {
        $import->update([
            'total_records' => $total,
            'success_count' => $success,
            'failed_count' => $failed,
        ]);
    }
}
