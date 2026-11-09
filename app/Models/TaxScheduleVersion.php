<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxScheduleVersion extends Model
{
    protected $fillable = ['effective_on', 'amount_minor', 'payment_details'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'payment_details' => 'array'];
    }
}
