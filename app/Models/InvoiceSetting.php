<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $company_name
 * @property string $company_address
 * @property string $company_email
 * @property string $company_phone
 * @property string $pib
 * @property string|null $iban
 * @property string|null $swift
 * @property string|null $logo_path
 * @property string $default_currency
 * @property int $default_due_days
 * @property string|null $footer_note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $mb
 * @property string $company_state
 * @property string $bank_account
 * @property string|null $foreign_currency_bank_account
 * @property string $logo
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\InvoiceSettingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereBankAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereCompanyAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereCompanyEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereCompanyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereCompanyPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereCompanyState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereDefaultCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereDefaultDueDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereFooterNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereForeignCurrencyBankAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereIban($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereLogoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereMb($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting wherePib($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereSwift($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceSetting whereUserId($value)
 * @mixin \Eloquent
 */
class InvoiceSetting extends Model {
    /** @use HasFactory<\Database\Factories\InvoiceSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_name',
        'company_address',
        'company_email',
        'company_phone',
        'company_state',
        'bank_account',
        'pib',
        'mb',
        'iban',
        'swift',
        'logo_path',
        'default_currency',
        'default_due_days',
        'footer_note',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo( User::class );
    }
}
