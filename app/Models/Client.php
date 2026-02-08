<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model {
    protected $fillable = [
        'name',
        'pib',
        'mb',
        'address',
        'city',
        'country',
        'email',
        'phone',
    ];

    public function company(): BelongsTo {
        return $this->belongsTo( Company::class );
    }

    public function invoices(): HasMany {
        return $this->hasMany( Invoice::class );
    }
}
