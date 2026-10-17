<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceEmailSend extends Model
{
    protected $fillable = [
        'company_id', 'invoice_id', 'invoice_pdf_version_id', 'recipient', 'subject',
        'message', 'status', 'failure_message', 'accepted_at', 'failed_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    public function pdfVersion(): BelongsTo { return $this->belongsTo(InvoicePdfVersion::class, 'invoice_pdf_version_id'); }
}
