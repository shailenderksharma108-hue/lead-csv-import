<?php

namespace App\Mail;

use App\Models\LeadImport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FailedRecordsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LeadImport $import,
        public string $failedCsvPath
    ) {}

    public function build()
    {
        return $this
            ->subject(
                'Lead CSV Import - Failed Records'
            )
            ->view('emails.failed_records')
            ->with([
                'import' => $this->import,
            ])
            ->attach(
                $this->failedCsvPath,
                [
                    'as' => 'failed_records_' . $this->import->id . '.csv',
                    'mime' => 'text/csv',
                ]
            );
    }
}
