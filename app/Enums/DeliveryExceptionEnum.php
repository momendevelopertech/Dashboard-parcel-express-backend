<?php

namespace App\Enums;

final class DeliveryExceptionEnum
{
    /**
     * Delivery exception types.
     */
    public const NO_ANSWER = 'NO_ANSWER';
    public const WRONG_CITY = 'WRONG_CITY';
    public const WRONG_NUMBER = 'WRONG_NUMBER';
    public const WRONG_ADDRESS = 'WRONG_ADDRESS';
    public const CANCELLED = 'CANCELLED';
    public const FUTURE_DELIVERY = 'FUTURE_DELIVERY';
    public const DELIVER_LATER_TODAY = 'DELIVER_LATER_TODAY';
    public const TOMORROW = 'TOMORROW';

    /**
     * Get all exception types as an array.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::NO_ANSWER,
            self::WRONG_CITY,
            self::WRONG_NUMBER,
            self::WRONG_ADDRESS,
            self::CANCELLED,
            self::FUTURE_DELIVERY,
            self::DELIVER_LATER_TODAY,
            self::TOMORROW,
        ];
    }

    /**
     * Get map of lowercase exception keys to uppercase enum values.
     *
     * @return array<string, string>
     */
    public static function getMapping(): array
    {
        return [
            'no_answer' => self::NO_ANSWER,
            'wrong_city' => self::WRONG_CITY,
            'wrong_number' => self::WRONG_NUMBER,
            'wrong_answer' => self::WRONG_NUMBER, // Alias
            'wrong_address' => self::WRONG_ADDRESS,
            'cancelled' => self::CANCELLED,
            'future_delivery' => self::FUTURE_DELIVERY,
            'deliver_later_today' => self::DELIVER_LATER_TODAY,
            'tomorrow' => self::TOMORROW,
        ];
    }

    /**
     * Normalize exception string to enum value.
     *
     * @param string $exception
     * @return string
     */
    public static function normalize(string $exception): string
    {
        $lower = strtolower(trim($exception));
        $mapping = self::getMapping();

        return $mapping[$lower] ?? strtoupper(trim($exception));
    }

    /**
     * Check if exception is valid.
     *
     * @param string $exception
     * @return bool
     */
    public static function isValid(string $exception): bool
    {
        return in_array(strtoupper($exception), self::all(), true);
    }

    /**
     * Get exceptions that should force CRM escalation.
     *
     * @return array<string>
     */
    public static function getCrmEscalationTypes(): array
    {
        return [
            self::WRONG_CITY,
            self::WRONG_NUMBER,
            self::NO_ANSWER,
            self::CANCELLED,
        ];
    }

    /**
     * Get exceptions that are mutable (can be changed).
     *
     * @return array<string>
     */
    public static function getMutableTypes(): array
    {
        return [
            self::DELIVER_LATER_TODAY,
        ];
    }

    /**
     * Check if exception requires date/time input.
     *
     * @param string $exception
     * @return bool
     */
    public static function requiresDateTime(string $exception): bool
    {
        return in_array(strtoupper($exception), [
            self::FUTURE_DELIVERY,
            self::DELIVER_LATER_TODAY,
            self::TOMORROW,
        ], true);
    }
}

