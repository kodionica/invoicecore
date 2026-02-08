<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model {
    public    $timestamps = false;
    protected $fillable   = [
        'name',
        'quantity',
        'price',
        'sub_total',
        'total',
        'tax_amount',
        'description',
    ];
    protected $casts      = [
        'quantity'   => 'decimal:2',
        'price'      => 'decimal:2',
        'sub_total'  => 'decimal:2',
        'total'      => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo {
        return $this->belongsTo( Invoice::class );
    }
}
