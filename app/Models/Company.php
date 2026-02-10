<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int                                                                     $id
 * @property string                                                                  $name
 * @property int|null                                                                $pib
 * @property int|null                                                                $mb
 * @property string|null                                                             $address
 * @property string|null                                                             $city
 * @property string|null                                                             $country
 * @property string|null                                                             $email
 * @property string|null                                                             $phone
 * @property string|null                                                             $bank_account
 * @property string|null                                                             $iban
 * @property string|null                                                             $swift
 * @property string|null                                                             $logo_path
 * @property int                                                                     $user_id
 * @property \Illuminate\Support\Carbon|null                                         $created_at
 * @property \Illuminate\Support\Carbon|null                                         $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Client>  $clients
 * @property-read int|null                                                           $clients_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null                                                           $invoices_count
 * @property-read \App\Models\CompanySettings|null                                   $settings
 * @property-read \App\Models\User|null                                              $users
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereAddress( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereBankAccount( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCity( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCountry( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCreatedAt( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereEmail( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereIban( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereId( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereLogoPath( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereMb( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereName( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company wherePhone( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company wherePib( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereSwift( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereUpdatedAt( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereUserId( $value )
 * @mixin \Eloquent
 */
class Company extends Model {
    protected $fillable = [
        'name',
        'tax_id',
        'company_id',
        'address',
        'city',
        'country',
        'email',
        'phone',
        'bank_account',
        'iban',
        'swift',
        'logo_path',
        'user_id',
    ];

    protected static function booted() {
        static::created( static fn( $company ) => $company->settings()->create() );
    }

    public function user(): BelongsTo {
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
