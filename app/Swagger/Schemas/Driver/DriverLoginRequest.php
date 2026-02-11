<?php

namespace App\Swagger\Schemas\Driver;

/**
 * @OA\Schema(
 *     title="DriverLoginRequest",
 *     description="Driver mobile app login request",
 *     type="object",
 *     required={"email", "password"},
 *     @OA\Xml(name="DriverLoginRequest")
 * )
 */
class DriverLoginRequest
{
    /**
     * @OA\Property(
     *     title="Email",
     *     description="Driver's email address",
     *     format="email",
     *     example="driver@gmail.com"
     * )
     *
     * @var string
     */
    public $email;

    /**
     * @OA\Property(
     *     title="Password",
     *     description="Driver's password",
     *     format="password",
     *     example="password123"
     * )
     *
     * @var string
     */
    public $password;
} 