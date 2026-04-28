<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int                                                                         $id
 * @property int                                                                         $company_id
 * @property int                                                                         $client_id
 * @property int                                                                         $number
 * @property string                                                                      $issue_date
 * @property string                                                                      $due_date
 * @property string                                                                      $currency
 * @property numeric|null                                                                $fx_rate_to_rsd
 * @property string|null                                                                 $fx_provider
 * @property string|null                                                                 $fx_date
 * @property string|null                                                                 $payment_method
 * @property numeric                                                                     $total
 * @property numeric                                                                     $subtotal_original
 * @property numeric                                                                     $tax_original
 * @property numeric                                                                     $total_original
 * @property numeric                                                                     $subtotal_rsd
 * @property numeric                                                                     $tax_rsd
 * @property numeric                                                                     $total_rsd
 * @property string                                                                      $status
 * @property string|null                                                                 $pdf_path
 * @property string|null                                                                 $note
 * @property \Illuminate\Support\Carbon|null                                             $created_at
 * @property \Illuminate\Support\Carbon|null                                             $updated_at
 * @property-read \App\Models\Client                                                     $client
 * @property-read \App\Models\Company                                                    $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceItem> $items
 * @property-read int|null                                                               $items_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereClientId( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCompanyId( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCreatedAt( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCurrency( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDueDate( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereId( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereIssueDate( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereNote( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereNumber( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePaymentMethod( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePdfPath( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereStatus( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTotal( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereUpdatedAt( $value )
 * @property string                                                                      $invoice_number
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereInvoiceNumber( $value )
 * @mixin \Eloquent
 */
class Invoice extends Model {
    protected $fillable = [
        'invoice_number',
        'issue_date',
        'due_date',
        'currency',
        'fx_rate_to_rsd',
        'fx_provider',
        'fx_date',
        'payment_method',
        'total',
        'subtotal_original',
        'tax_original',
        'total_original',
        'subtotal_rsd',
        'tax_rsd',
        'total_rsd',
        'status',
        'pdf_path',
        'note',
        'company_id',
        'client_id',
    ];
    protected $casts    = [
        'issue_date' => 'date',
        'due_date'   => 'date',
        'fx_date'    => 'date',
        'fx_rate_to_rsd' => 'decimal:6',
        'total'      => 'decimal:2',
        'subtotal_original' => 'decimal:2',
        'tax_original'      => 'decimal:2',
        'total_original'    => 'decimal:2',
        'subtotal_rsd'      => 'decimal:2',
        'tax_rsd'           => 'decimal:2',
        'total_rsd'         => 'decimal:2',
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

    public function scopeForActiveCompany( Builder $query, int $companyId ): Builder {
        return $query->where( 'company_id', $companyId );
    }
}
