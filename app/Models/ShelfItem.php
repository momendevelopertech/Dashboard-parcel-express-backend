<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ShelfItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shelf_id', 'position', 'item_name','barcode'
    ];

    // Define the relationship to Shelf
    public function shelf()
    {
        return $this->belongsTo(Shelf::class);
    }
}
