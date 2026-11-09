<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxObligation extends Model
{
    protected $fillable = [
        'company_id', 'tax_schedule_version_id', 'period_start', 'period_end',
        'due_on', 'remind_on', 'amount_minor', 'note',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TaxSchedule::class, 'tax_schedule_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(TaxScheduleVersion::class, 'tax_schedule_version_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TaxPayment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaxAttachment::class);
    }

    public function outstanding(): ?int
    {
        return $this->amount_minor === null ? null : $this->amount_minor - (int) $this->payments_sum_amount_minor;
    }
}
