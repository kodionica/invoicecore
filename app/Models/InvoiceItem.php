<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string $name
 * @property numeric $quantity
 * @property numeric $price
 * @property numeric $sub_total
 * @property numeric $total
 * @property numeric $tax_amount
 * @property string $description
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property-read \App\Models\Invoice $invoice
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereSubTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereTaxAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
