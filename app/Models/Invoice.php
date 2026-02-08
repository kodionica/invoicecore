<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model {
    protected $fillable = [
        'number',
        'issue_date',
        'due_date',
        'currency',
        'total',
        'status',
        'pdf_path',
        'note',
    ];

    public function items(): HasMany {
        return $this->hasMany( InvoiceItem::class );
    }

    public function client(): BelongsTo {
        return $this->belongsTo( Client::class );
    }

    public function company(): BelongsTo {
        return $this->belongsTo( Company::class );
    }
}
