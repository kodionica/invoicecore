<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property int|null $pib
 * @property int|null $mb
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string|null $email
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereMb($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client wherePib($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Client whereUpdatedAt($value)
 */
	class Client extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property int|null $pib
 * @property int|null $mb
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $bank_account
 * @property string|null $iban
 * @property string|null $swift
 * @property string|null $logo_path
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Client> $clients
 * @property-read int|null $clients_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \App\Models\CompanySettings|null $settings
 * @property-read \App\Models\User|null $users
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereBankAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereIban($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereLogoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereMb($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company wherePib($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereSwift($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Company whereUserId($value)
 */
	class Company extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $company_id
 * @property string $invoice_prefix
 * @property int $invoice_start_number
 * @property int $invoice_next_number
 * @property string $currency
 * @property int $default_tax_percent
 * @property bool $vat_enabled
 * @property int $payment_due_days
 * @property string|null $invoice_note
 * @property string|null $other_settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereDefaultTaxPercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereInvoiceNextNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereInvoiceNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereInvoicePrefix($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereInvoiceStartNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereOtherSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings wherePaymentDueDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompanySettings whereVatEnabled($value)
 */
	class CompanySettings extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $company_id
 * @property int $client_id
 * @property int $number
 * @property string $issue_date
 * @property string $due_date
 * @property string $currency
 * @property string|null $payment_method
 * @property numeric $total
 * @property string $status
 * @property string|null $pdf_path
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Client $client
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceItem> $items
 * @property-read int|null $items_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereIssueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePdfPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereUpdatedAt($value)
 */
	class Invoice extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $invoice_id
 * @property string $name
 * @property numeric $quantity
 * @property numeric $price
 * @property numeric $sub_total
 * @property numeric $total
 * @property numeric $tax_amount
 * @property string $description
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property-read \App\Models\Invoice $invoice
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereSubTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereTaxAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereUpdatedAt($value)
 */
	class InvoiceItem extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string|null $created_at
 * @property string|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereValue($value)
 */
	class Setting extends \Eloquent {}
}

namespace App\Models{
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
 * @mixin \Eloquent
 * @property string $first_name
 * @property string $last_name
 * @property string|null $phone
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Company> $companies
 * @property-read int|null $companies_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 */
	class User extends \Eloquent {}
}

