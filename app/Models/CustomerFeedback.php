<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerFeedback extends Model
{
    protected $fillable = ['delivery_id', 'rating', 'comments'];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
