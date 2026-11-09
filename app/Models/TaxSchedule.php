<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxSchedule extends Model
{
    public const CATEGORIES = ['Porez', 'PIO', 'Zdravstveno', 'Nezaposlenost'];

    protected $fillable = [
        'company_id', 'request_id', 'request_fingerprint', 'category', 'name', 'starts_on', 'frequency', 'period_count',
        'due_day', 'due_month_offset', 'due_on', 'roll_forward', 'reminder_days', 'note',
    ];

    protected $hidden = ['request_id', 'request_fingerprint'];

    protected function casts(): array
    {
        return [
            'roll_forward' => 'boolean',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TaxScheduleVersion::class);
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(TaxObligation::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaxAttachment::class)->whereNull('tax_obligation_id');
    }
}
