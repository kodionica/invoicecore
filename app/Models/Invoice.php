<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model {
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'client_id', 'invoice_number',
        'invoice_date', 'due_date', 'currency', 'total_amount',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo( User::class );
    }

    public function client(): BelongsTo {
        return $this->belongsTo( Client::class );
    }

    public function items(): HasMany {
        return $this->hasMany( InvoiceItem::class );
    }

    public function payments(): HasMany {
        return $this->hasMany( Payment::class );
    }
}
