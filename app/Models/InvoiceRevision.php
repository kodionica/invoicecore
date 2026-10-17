<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceRevision extends Model
{
    protected $fillable = [
        'invoice_id',
        'acting_user_id',
        'action_type',
        'previous_values',
        'new_values',
        'changed_fields',
    ];

    protected $casts = [
        'previous_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function actingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acting_user_id');
    }
}
