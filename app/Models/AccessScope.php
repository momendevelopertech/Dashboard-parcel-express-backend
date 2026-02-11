<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessScope extends Model
{
    protected $fillable = ['owner_type', 'owner_id', 'name'];

    public function accessScope()
    {
        return $this->morphOne(AccessScope::class, 'owner');
    }

    public function allowedScopes()
    {
        return $this->belongsToMany(AccessScope::class, 'access_scope_user');
    }
}
