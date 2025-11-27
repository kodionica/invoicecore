<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model {
    /** @use HasFactory<\Database\Factories\InvoiceItemFactory> */
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'description', 'quantity', 'unit_price', 'total',
    ];

    public function invoice(): BelongsTo {
        return $this->belongsTo( Invoice::class );
    }
}
