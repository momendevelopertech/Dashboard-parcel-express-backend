<?php

namespace App\Support;

class OwnerMap
{
    public const MAP = [
        'station' => \App\Models\Station::class,
        'stations' => \App\Models\Station::class,
        'hub' => \App\Models\Hub::class,
        'hubs' => \App\Models\Hub::class,
        'branch' => \App\Models\Branch::class,
        'branches' => \App\Models\Branch::class,
    ];

    public static function normalize(?string $type): ?string
    {
        if (!$type)
            return null;
        $key = strtolower(trim($type));
        return self::MAP[$key] ?? null;
    }
}
