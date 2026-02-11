<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;

class PartnerApiErrorResource
{
    public static function make(
        string $code,
        string $message,
        array $details = [],
        int $status = 400
    ): JsonResponse {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ], $status);
    }

    public static function notFound(string $resource, string $id): JsonResponse
    {
        return self::make(
            'resource_not_found',
            "{$resource} not found",
            [$resource . '_id' => $id],
            404
        );
    }

    public static function unauthorized(string $message = 'Authentication required'): JsonResponse
    {
        return self::make('unauthorized', $message, [], 401);
    }

    public static function forbidden(string $message = 'Insufficient permissions'): JsonResponse
    {
        return self::make('forbidden', $message, [], 403);
    }

    public static function rateLimited(int $retryAfter): JsonResponse
    {
        return self::make(
            'rate_limit_exceeded',
            'Rate limit exceeded',
            ['retry_after_seconds' => $retryAfter],
            429
        );
    }

    public static function invalidParameter(string $parameter, string $reason): JsonResponse
    {
        return self::make(
            'invalid_parameter',
            "Invalid parameter: {$parameter}",
            ['parameter' => $parameter, 'reason' => $reason],
            400
        );
    }
}