<?php

namespace App\Services;
class PartnerAuthService
{
    public function generateSignature(
        string $method,
        string $path,
        string $timestamp,
        string $body,
        string $secret
    ): string {
        $bodyHash = hash('sha256', $body);
        $stringToSign = "{$method}\n{$path}\n{$timestamp}\n{$bodyHash}";

        return hash_hmac('sha256', $stringToSign, $secret);
    }

    public function verifySignature(
        string $signature,
        string $method,
        string $path,
        string $timestamp,
        string $body,
        string $secret
    ): bool {
        $expectedSignature = $this->generateSignature($method, $path, $timestamp, $body, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    public function isTimestampValid(string $timestamp, int $toleranceSeconds = 300): bool
    {
        try {
            $requestTime = \Carbon\Carbon::parse($timestamp);
            $now = now();
            $diff = abs($now->diffInSeconds($requestTime));

            return $diff <= $toleranceSeconds;
        } catch (\Exception $e) {
            return false;
        }
    }
}