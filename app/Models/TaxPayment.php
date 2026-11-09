<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxPayment extends Model
{
    protected $fillable = ['request_id', 'amount_minor', 'paid_on', 'note'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }
}
