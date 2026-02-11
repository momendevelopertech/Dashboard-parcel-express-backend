<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\ScheduledMessageLog;

class ScheduledMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_title',
        'channel',
        'body',
        'recipients',
        'scheduled_at',
        'status',
    ];

    protected $casts = [
        'recipients' => 'array',
        'scheduled_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(ScheduledMessageLog::class);
    }
}
