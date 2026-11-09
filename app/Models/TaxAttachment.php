<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxAttachment extends Model
{
    protected $fillable = ['tax_schedule_id', 'tax_obligation_id', 'name', 'path'];

    protected $hidden = ['path'];
}
