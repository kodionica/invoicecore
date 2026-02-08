<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model {
    protected $fillable = [
        'name',
        'pib',
        'mb',
        'address',
        'city',
        'country',
        'email',
        'phone',
        'bank_account',
        'iban',
        'swift',
        'logo_path',
    ];

    protected static function booted() {
        static::created( static fn( $company ) => $company->settings()->create() );
    }

    public function users(): BelongsTo {
        return $this->belongsTo( User::class );
    }

    public function settings(): HasOne {
        return $this->hasOne( CompanySettings::class );
    }

    public function clients(): HasMany {
        return $this->hasMany( Client::class );
    }

    public function invoices(): HasMany {
        return $this->hasMany( Invoice::class );
    }
}
