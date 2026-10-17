<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpoEntryRevision extends Model
{
    protected $fillable = [
        'kpo_entry_id',
        'acting_user_id',
        'action_type',
        'previous_values',
        'new_values',
        'manual_edit_overwritten',
        'operation_id',
    ];

    protected $casts = [
        'previous_values' => 'array',
        'new_values' => 'array',
        'manual_edit_overwritten' => 'boolean',
    ];

    public function kpoEntry(): BelongsTo
    {
        return $this->belongsTo(KpoEntry::class);
    }

    public function actingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acting_user_id');
    }
}
