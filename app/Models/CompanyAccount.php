<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyAccount extends Model
{
    protected $fillable = [
        'company_id',
        'balance',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}


