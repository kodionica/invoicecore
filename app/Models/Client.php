<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model {
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'email', 'address',
        'country', 'vat_number', 'company_number',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo( User::class );
    }

    public function invoices(): HasMany {
        return $this->hasMany( Invoice::class );
    }
}
