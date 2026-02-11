<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
// use Illuminate\Support\Facades\Redis;
class RateLimitPartner
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $partner = $request->attributes->get('partner');
        $limits = array_merge([
            'rpm' => (int) env('PARTNERAPI_RPM_DEFAULT', 120),
            'burst' => (int) env('PARTNERAPI_BURST', 200),
            'daily' => (int) env('PARTNERAPI_DAILY_QUOTA', 150000),
        ], $partner->rate_limit ?? []);
        $minuteKey = sprintf('rl:%d:%s', $partner->id, now()->format('YmdHi'));
        $dayKey = sprintf('quota:%d:%s', $partner->id, now()->format('Ymd'));
        // $minCount = (int) Redis::incr($minuteKey);
        // if ($minCount === 1)
        //     Redis::expire($minuteKey, 65);
        // $dayCount = (int) Redis::incr($dayKey);
        // if ($dayCount === 1)
        //     Redis::expire($dayKey, 86465);
        // if ($minCount > $limits['rpm'] + $limits['burst'] || $dayCount > $limits['daily']) {
        //     return response()->json([
        //         'code' => 'rate_limit_exceeded',
        //         'message' => 'Rate limit exceeded',
        //         'retry_after_seconds' => 60
        //     ], 429);
        // }

        return $next($request);
    }
}
