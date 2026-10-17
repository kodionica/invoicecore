<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoicePdfVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id', 'invoice_id', 'version', 'path', 'created_at', 'protected_at', 'sent_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'created_at' => 'datetime',
        'protected_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    public function emailSends(): HasMany { return $this->hasMany(InvoiceEmailSend::class, 'invoice_pdf_version_id'); }
}
