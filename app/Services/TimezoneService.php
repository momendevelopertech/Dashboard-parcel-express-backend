<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TimezoneService
{
    /**
     * Get the effective timezone for the current user/operation
     *
     * @param User|null $user
     * @return string
     */
    public function getEffectiveTimezone(?User $user = null): string
    {
        $user ??= Auth::user();

        // Priority 1: User's explicit timezone preference
        if ($user && $user->timezone) {
            return $user->timezone;
        }

        // Priority 2: Hub timezone for sorters
        if ($user && $user->hasRole('Sorter')) {
            $hubTimezone = sorter_hub_timezone($user);
            if ($hubTimezone) {
                return $hubTimezone;
            }
        }

        // Priority 3: Application default
        return config('app.timezone', 'UTC');
    }

    /**
     * Get current time in the effective timezone
     *
     * @param User|null $user
     * @return Carbon
     */
    public function now(?User $user = null): Carbon
    {
        $timezone = $this->getEffectiveTimezone($user);
        return Carbon::now($timezone);
    }

    /**
     * Convert a timestamp from one timezone to another
     *
     * @param Carbon $timestamp
     * @param string $fromTimezone
     * @param string $toTimezone
     * @return Carbon
     */
    public function convert(Carbon $timestamp, string $fromTimezone, string $toTimezone): Carbon
    {
        return $timestamp->copy()
            ->setTimezone($fromTimezone)
            ->setTimezone($toTimezone);
    }

    /**
     * Parse a date string in a specific timezone and return UTC range
     *
     * @param string $date
     * @param string $timezone
     * @return array
     */
    public function parseToUtcRange(string $date, string $timezone): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $date, $timezone)->endOfDay();

        return [
            $start->copy()->setTimezone('UTC'),
            $end->copy()->setTimezone('UTC')
        ];
    }

    /**
     * Create a timezone-aware timestamp with metadata
     *
     * @param User|null $user
     * @return array
     */
    public function createTimestamp(?User $user = null): array
    {
        $timezone = $this->getEffectiveTimezone($user);
        $now = Carbon::now($timezone);

        return [
            'timestamp' => $now,
            'timezone' => $timezone,
            'utc_timestamp' => $now->copy()->setTimezone('UTC'),
        ];
    }

    /**
     * Validate timezone identifier
     *
     * @param string $timezone
     * @return bool
     */
    public function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, \DateTimeZone::listIdentifiers());
    }

    /**
     * Get timezone from request or user preference
     *
     * @param string|null $requestTimezone
     * @param User|null $user
     * @return string
     */
    public function resolveFromRequest(?string $requestTimezone = null, ?User $user = null): string
    {
        // Priority 1: Explicit timezone in request (if valid)
        if ($requestTimezone && $this->isValidTimezone($requestTimezone)) {
            return $requestTimezone;
        }

        // Priority 2: Timezone from current access token
        $tokenTimezone = $this->getTimezoneFromToken();
        if ($tokenTimezone) {
            return $tokenTimezone;
        }

        // Priority 3: User's effective timezone
        return $this->getEffectiveTimezone($user);
    }

    /**
     * Resolve timezone for login (from request or user preference)
     *
     * @param string|null $requestTimezone
     * @param User|null $user
     * @return string
     */
    public function resolveTimezoneForLogin(?string $requestTimezone, ?User $user): string
    {
        // Priority 1: Valid timezone from login request
        if ($requestTimezone && $this->isValidTimezone($requestTimezone)) {
            return $requestTimezone;
        }

        // Priority 2: User's saved timezone preference
        if ($user && $user->timezone) {
            return $user->timezone;
        }

        // Priority 3: Hub timezone for sorters
        if ($user && $user->hasRole('Sorter')) {
            $hubTimezone = sorter_hub_timezone($user);
            if ($hubTimezone) {
                return $hubTimezone;
            }
        }

        // Priority 4: Default
        return config('app.timezone', 'Asia/Muscat');
    }

    /**
     * Get timezone from current user's access token
     *
     * @return string|null
     */
    public function getTimezoneFromToken(): ?string
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        $token = $user->currentAccessToken();

        if ($token && isset($token->timezone)) {
            return $token->timezone;
        }

        return null;
    }

    /**
     * Get list of all available timezones with offset information
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAvailableTimezones()
    {
        return collect(\DateTimeZone::listIdentifiers())
            ->map(function ($tz) {
                $now = Carbon::now($tz);
                return [
                    'identifier' => $tz,
                    'offset' => $now->format('P'),
                    'offset_seconds' => $now->getOffset(),
                    'display_name' => $tz . ' (UTC' . $now->format('P') . ')',
                ];
            })
            ->sortBy('offset_seconds')
            ->values();
    }
}
