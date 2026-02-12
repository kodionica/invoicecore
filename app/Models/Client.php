<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int                                                                     $id
 * @property int                                                                     $company_id
 * @property string                                                                  $name
 * @property int|null                                                                $pib
 * @property int|null                                                                $mb
 * @property string|null                                                             $address
 * @property string|null                                                             $city
 * @property string|null                                                             $country
 * @property string|null                                                             $email
 * @property string|null                                                             $phone
 * @property \Illuminate\Support\Carbon|null                                         $created_at
 * @property \Illuminate\Support\Carbon|null                                         $updated_at
 * @property-read \App\Models\Company                                                $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null                                                           $invoices_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereAddress( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereCity( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereCompanyId( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereCountry( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereCreatedAt( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereEmail( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereId( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereMb( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereName( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client wherePhone( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client wherePib( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereUpdatedAt( $value )
 * @mixin \Eloquent
 */
class Client extends Model {
    protected $fillable = [
        'name',
        'tax_id',
        'registration_number',
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
