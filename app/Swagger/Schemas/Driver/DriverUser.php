<?php

namespace App\Swagger\Schemas\Driver;

/**
 * @OA\Schema(
 *     title="DriverUser",
 *     description="Driver user information",
 *     @OA\Xml(name="DriverUser")
 * )
 */
class DriverUser
{
    /**
     * @OA\Property(
     *     title="ID",
     *     description="Driver user ID",
     *     format="int64",
     *     example=15
     * )
     *
     * @var integer
     */
    public $id;

    /**
     * @OA\Property(
     *     title="Name",
     *     description="Driver's full name",
     *     example="Ahmed Ali"
     * )
     *
     * @var string
     */
    public $name;

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
     *     title="Token",
     *     description="Personal access token for API authentication",
     *     example="45|7H2kF9mN8QvR6pL3..."
     * )
     *
     * @var string
     */
    public $token;

    /**
     * @OA\Property(
     *     title="Phone",
     *     description="Driver's phone number",
     *     example="+1234567890"
     * )
     *
     * @var string
     */
    public $phone;

    /**
     * @OA\Property(
     *     title="Role",
     *     description="Driver role information",
     *     type="object",
     *     @OA\Property(property="id", type="integer", example=3),
     *     @OA\Property(property="name", type="string", example="Driver")
     * )
     *
     * @var object
     */
    public $role;
} 