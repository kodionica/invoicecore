<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySettings extends Model {
    protected $fillable = [
        'invoice_prefix',
        'invoice_start_number',
        'invoice_next_number',
        'currency',
        'default_tax_percent',
        'vat_enabled',
        'payment_due_days',
        'invoice_note',
        'other_settings',
    ];
    protected $casts    = [
        'vat_enabled' => 'boolean',
    ];

    public function company(): BelongsTo {
        return $this->belongsTo( Company::class );
    }
}
