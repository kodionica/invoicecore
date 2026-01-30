<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceSetting extends Model {
    /** @use HasFactory<\Database\Factories\InvoiceSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'company_name', 'company_address', 'company_email',
        'company_phone', 'pib', 'iban', 'swift', 'logo_path',
        'default_currency', 'default_due_days', 'footer_note',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo( User::class );
    }
}
