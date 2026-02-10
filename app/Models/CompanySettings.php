<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                             $company_id
 * @property string                          $invoice_prefix
 * @property int                             $invoice_start_number
 * @property int                             $invoice_next_number
 * @property string                          $currency
 * @property int                             $default_tax_percent
 * @property bool                            $vat_enabled
 * @property int                             $payment_due_days
 * @property string|null                     $invoice_note
 * @property string|null                     $other_settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company        $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereCompanyId( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereCreatedAt( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereCurrency( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereDefaultTaxPercent( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereInvoiceNextNumber( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereInvoiceNote( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereInvoicePrefix( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereInvoiceStartNumber( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereOtherSettings( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings wherePaymentDueDays( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereUpdatedAt( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereVatEnabled( $value )
 * @mixin \Eloquent
 */
class CompanySettings extends Model {
    // Company ID is the foreign key and primary key.
    protected $primaryKey = 'company_id';
    protected $fillable   = [
        'invoice_prefix',
        'invoice_start_number',
        'invoice_next_number',
        'currency',
        'default_tax_percent',
        'vat_enabled',
        'payment_due_days',
        'invoice_note',
        'other_settings',
    ];
    protected $casts      = [
        'vat_enabled' => 'boolean',
    ];

    public function company(): BelongsTo {
        return $this->belongsTo( Company::class );
    }
}
