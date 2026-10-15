<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpoEntry extends Model
{
    protected $fillable = [
        'company_id',
        'source_invoice_id',
        'year',
        'entry_number',
        'entry_date',
        'source_type',
        'income_type',
        'currency',
        'amount_original',
        'amount_rsd',
        'adjustment_rsd',
        'total_rsd',
        'description',
    ];

    protected $casts = [
        'year' => 'integer',
        'entry_number' => 'integer',
        'entry_date' => 'date',
        'amount_original' => 'decimal:2',
        'amount_rsd' => 'decimal:2',
        'adjustment_rsd' => 'decimal:2',
        'total_rsd' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'source_invoice_id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }
}
