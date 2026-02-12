<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int                                                                                                                $id
 * @property string                                                                                                             $name
 * @property string                                                                                                             $email
 * @property \Illuminate\Support\Carbon|null                                                                                    $email_verified_at
 * @property string                                                                                                             $password
 * @property string|null                                                                                                        $remember_token
 * @property \Illuminate\Support\Carbon|null                                                                                    $created_at
 * @property \Illuminate\Support\Carbon|null                                                                                    $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Client>                                             $clients
 * @property-read int|null                                                                                                      $clients_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceItem>                                        $invoiceItems
 * @property-read int|null                                                                                                      $invoice_items_count
 * @property-read \App\Models\InvoiceSetting|null                                                                               $invoiceSettings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice>                                            $invoices
 * @property-read int|null                                                                                                      $invoices_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null                                                                                                      $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment>                                            $payments
 * @property-read int|null                                                                                                      $payments_count
 * @method static \Database\Factories\UserFactory factory( $count = null, $state = [] )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt( $value )
 * @property string                                                                                                             $first_name
 * @property string                                                                                                             $last_name
 * @property string|null                                                                                                        $phone
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Company>                                            $companies
 * @property-read int|null                                                                                                      $companies_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName( $value )
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone( $value )
 * @mixin \Eloquent
 */
class User extends Authenticatable {
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'password',
        'username',
        'active_company_id',
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    protected $appends = [
        'display_name',
    ];

    public function getDisplayNameAttribute(): string {
        if ( $this->first_name && $this->last_name ) {
            return $this->first_name . ' ' . $this->last_name;
        }

        if ( $this->username ) {
            return $this->username;
        }

        if ( $this->email ) {
            return $this->email;
        }

        return 'Guest';
    }

    /**
     * Generate a username from a base string with checks for existing usernames.
     *
     * @param string $base
     *
     * @return string
     */
    public static function generateUsername( string $base ): string {

        $base = strtolower( preg_replace( '/[^a-z0-9._]/', '', strstr( $base, '@', true ) ) );

        $username = $base;
        $counter  = 1;

        // Add a number to the username if it already exists
        while ( self::where( 'username', $username )->exists() ) {
            $counter++;
        }

        return $base . $counter;
    }

    public function companies(): HasMany {
        return $this->hasMany( Company::class );
    }

    public function activeCompany(): BelongsTo {
        return $this->belongsTo( Company::class, 'active_company_id' );
    }
}
