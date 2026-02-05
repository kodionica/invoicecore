<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model {
    protected $fillable = [
        'company_id',
        'client_id',
        'number',
        'issue_date',
        'due_date',
        'total',
        'status',
        'pdf_path',
    ];

    public function items(): HasMany {
        return $this->hasMany( InvoiceItem::class );
    }

    public function client(): BelongsTo {
        return $this->belongsTo( Client::class );
    }
}
