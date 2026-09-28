<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadImportFailure extends Model
{
    protected $fillable = [
        'lead_import_id',
        'row_number',
        'name',
        'email',
        'phone',
        'company',
        'reason',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(
            LeadImport::class,
            'lead_import_id'
        );
    }
}
