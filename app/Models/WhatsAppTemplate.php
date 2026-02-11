<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    protected $fillable = [
        "name",
        "message",
    ];
    
}
