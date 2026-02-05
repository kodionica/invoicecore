<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySettings extends Model {
    protected $fillable = [
        'company_id',
        'invoice_prefix',
        'next_invoice_number',
        'address',
        'city',
        'country',
        'email',
        'phone',
        'bank_account',
        'iban',
        'swift',
        'logo_path',
        'default_currency',
        'vat_enabled',
        'default_due_days',
        'footer_note',
    ];
    protected $casts    = [
        'vat_enabled' => 'boolean',
    ];

    public function company(): BelongsTo {
        return $this->belongsTo( Company::class );
    }
}
