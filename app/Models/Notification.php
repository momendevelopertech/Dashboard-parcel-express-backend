<?php

namespace App\Models;

use App\Observers\NotificationObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Support\Str;

#[ObservedBy([NotificationObserver::class])]
class Notification extends Model
{
    protected $table = 'notifications';
    protected $primaryKey = 'id';

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'type', 'title', 'content', 'notifiable_id', 'notifiable_type', 'data', 'read_at'];
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];
    public function getTitleAttribute($value)
    {
        return $value ?? data_get($this->data, 'title');
    }

    protected $appends = ['content'];

    public function getContentAttribute($value)
    {
        if (is_string($value) && strlen(trim($value)) > 0) {
            return $value;
        }
        foreach (['content', 'body', 'message', 'text'] as $k) {
            $v = data_get($this->data, $k);
            if (is_string($v) && strlen(trim($v)) > 0)
                return $v;
        }
        return null;
    }
    public function notifiable()
    {
        return $this->morphTo();
    }
}
